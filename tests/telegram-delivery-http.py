"""Real HTTP delivery regression with no cron, follow-up traffic or manual runner."""
import argparse
from concurrent.futures import ThreadPoolExecutor, as_completed
import json
import subprocess
import threading
import time
import urllib.error
import urllib.request
import uuid

CONTAINER = "joyrent-telegram-qa-wp"
BOOKING_URL = "http://127.0.0.1:39181/wp-json/joyrent/v1/requests"
SUBSCRIBERS = 2


def php(*args):
    result = subprocess.run(
        ["docker", "exec", CONTAINER, "php",
         "/task/tests/php/telegram-delivery-http.php", *map(str, args)],
        capture_output=True, text=True, check=True, timeout=10,
    )
    return result.stdout


def request_bookings(payloads):
    """Release every distinct HTTP request together, including duplicate replays."""
    barrier = threading.Barrier(len(payloads))

    def book(index, payload):
        request = urllib.request.Request(
            BOOKING_URL, data=json.dumps(payload).encode(),
            headers={"Content-Type": "application/json",
                     "X-JOYRENT-QA-Peer": f"192.0.2.{index + 1}"},
        )
        barrier.wait(timeout=5)
        start = time.monotonic()
        try:
            response = urllib.request.urlopen(request, timeout=15)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            receipt = json.loads(response.read())
            return payload, response.status, receipt, time.monotonic() - start

    results, errors = [], []
    with ThreadPoolExecutor(max_workers=len(payloads)) as pool:
        futures = [pool.submit(book, index, payload)
                   for index, payload in enumerate(payloads)]
        for future in as_completed(futures):
            try:
                results.append(future.result())
            except Exception as error:
                errors.append(error)
    return results, errors


def finished(snapshot, order_ids, baseline):
    for order_id in order_ids:
        entry = snapshot["orders"][str(order_id)]
        if baseline:
            if entry["status"] is not None or entry["recipients"]:
                return False
        elif entry["status"] != "sent" or entry["recipients"] != {"sent": SUBSCRIBERS}:
            return False
    return True


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--burst", action="store_true",
                        help="Create five distinct bookings simultaneously.")
    parser.add_argument("--baseline", action="store_true",
                        help="Disable Telegram to measure booking HTTP overhead.")
    args = parser.parse_args()
    count = 5 if args.burst else 1
    deadline_seconds = 15 if args.burst else 6
    fixture = json.loads(php("init"))
    assert fixture["cronDisabled"] is True
    if args.baseline:
        assert json.loads(php("disable"))["telegramEnabled"] is False
    payloads = [{
        "language": "ru", "console": "ps5", "days": 3,
        "startDate": fixture["startDate"], "controllers": 2, "gameIds": [],
        "name": f"Immediate QA Fixture {index + 1}",
        "phone": "+380500000077", "telegram": "@qa_fixture",
        "method": "delivery", "address": "Фонтанская дорога, 10",
        "securityMode": "deposit", "consent": True, "website": "",
        "requestId": str(uuid.uuid4()),
    } for index in range(count)]
    order_ids = []
    started = time.monotonic()
    try:
        results, errors = request_bookings(payloads)
        order_ids = [
            int(receipt["reference"][3:])
            for _, status, receipt, _ in results
            if status == 201 and "reference" in receipt
        ]
        assert not errors, f"Booking HTTP requests failed: {errors}"
        summaries = [{"status": status, "reference": receipt.get("reference"),
                      "error": receipt.get("code"), "seconds": round(elapsed, 3)}
                     for _, status, receipt, elapsed in results]
        assert len(order_ids) == count, f"Booking burst was not accepted: {summaries}"
        assert len(set(order_ids)) == count, "Different request identities reused an order"
        assert all(status == 201 for _, status, _, _ in results), summaries
        response_times = [elapsed for _, _, _, elapsed in results]
        if not args.baseline:
            assert max(response_times) < 2.5, (
                f"Booking responses blocked by delivery: {response_times}"
            )
        expected_sends = 0 if args.baseline else count * SUBSCRIBERS
        max_dispatches = 0 if args.baseline else expected_sends + 2
        deadline = started + deadline_seconds
        last = None
        polls = 0
        while time.monotonic() < deadline:
            last = json.loads(php("poll-many", *order_ids))
            polls += 1
            assert last["dispatchRequests"] <= max_dispatches, (
                f"Background worker launch count exceeded load bound: {last}"
            )
            assert last["sends"] <= expected_sends, (
                f"Duplicate deliveries during background processing: {last}"
            )
            if finished(last, order_ids, args.baseline):
                break
            time.sleep(0.2)
        delivery_seconds = time.monotonic() - started
        assert last is not None and finished(last, order_ids, args.baseline), (
            f"Background notification state was unexpected: {last}"
        )
        assert delivery_seconds <= deadline_seconds, (
            f"Delivery exceeded {deadline_seconds}s bound: {delivery_seconds:.3f}s"
        )
        assert last["sends"] == expected_sends, (
            f"Recipient acknowledgments disagree with API send count: {last}"
        )
        references = {payload["requestId"]: receipt["reference"]
                      for payload, _, receipt, _ in results}
        repeats, errors = request_bookings(payloads)
        assert not errors, f"Booking replay HTTP requests failed: {errors}"
        assert len(repeats) == count and all(
            status == 200 and receipt["reference"] == references[payload["requestId"]]
            for payload, status, receipt, _ in repeats
        ), "Replay changed a booking identity or did not return HTTP 200"
        # Give delayed follow-up requests time to expose an accidental requeue.
        time.sleep(1)
        repeated = json.loads(php("poll-many", *order_ids))
        assert repeated["sends"] == expected_sends, (
            f"Booking replay duplicated delivery: {repeated}"
        )
        assert repeated["dispatchRequests"] <= max_dispatches, (
            f"Replay caused excessive background worker launches: {repeated}"
        )
        assert repeated["pendingJobs"] == 0, (
            f"Acknowledged deliveries left background work queued: {repeated}"
        )
        assert finished(repeated, order_ids, args.baseline), (
            f"Replay changed acknowledged recipient states: {repeated}"
        )
        stores = {entry["store"] for entry in repeated["orders"].values()}
        assert len(stores) == 1, f"Bookings mixed order stores: {stores}"
        print(json.dumps({
            "pass": True, "mode": "burst" if args.burst else "solo",
            "baseline": args.baseline,
            "bookings": count, "independentPeers": count,
            "responseSeconds": round(max(response_times), 3),
            "responseSecondsEach": sorted(round(value, 3) for value in response_times),
            "deliverySeconds": None if args.baseline else round(delivery_seconds, 3),
            "deadlineSeconds": deadline_seconds, "pollRounds": polls,
            "sends": repeated["sends"], "store": stores.pop(),
            "dispatchRequests": repeated["dispatchRequests"],
            "pendingJobs": repeated["pendingJobs"],
            "cronDisabled": True, "manualRunner": False, "duplicateReplay": True,
        }))
    finally:
        cleaned = json.loads(php("cleanup-requests",
                                 *(payload["requestId"] for payload in payloads)))
        assert cleaned["remainingFixtures"] == 0 and cleaned["pendingFixtureJobs"] == 0, (
            f"Fixture cleanup was incomplete: {cleaned}"
        )


if __name__ == "__main__":
    main()
