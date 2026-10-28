"""Минимальный драйвер Chrome по CDP: навигация, клик, наведение, скриншоты.

Запуск Chrome — headless с удалённой отладкой; профиль сохраняется, поэтому
сессия администратора Drupal (полученная по одноразовой ссылке drush uli)
живёт между запусками.
"""
import json
import os
import subprocess
import time
import urllib.request

import websocket

CHROME = "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"


class Chrome:
    def __init__(self, profile_dir, port=9333, width=1440, height=1000, scale=2):
        self.port = port
        self.width = width
        self.height = height
        self.scale = scale
        os.makedirs(profile_dir, exist_ok=True)
        self.proc = subprocess.Popen(
            [
                CHROME,
                "--headless=new",
                "--disable-gpu",
                "--no-first-run",
                "--no-default-browser-check",
                "--hide-scrollbars",
                f"--user-data-dir={profile_dir}",
                f"--remote-debugging-port={port}",
                "--remote-allow-origins=*",
                f"--window-size={width},{height}",
                "about:blank",
            ],
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
        self.ws = None
        self._id = 0
        self._wait_debugger()

    def _wait_debugger(self, timeout=30):
        deadline = time.time() + timeout
        while time.time() < deadline:
            try:
                data = json.load(urllib.request.urlopen(f"http://127.0.0.1:{self.port}/json/list", timeout=2))
                pages = [t for t in data if t.get("type") == "page"]
                if not pages:
                    # headless=new иногда стартует без страницы — создаём вкладку явно.
                    try:
                        req = urllib.request.Request(
                            f"http://127.0.0.1:{self.port}/json/new?about:blank", method="PUT"
                        )
                        pages = [json.load(urllib.request.urlopen(req, timeout=3))]
                    except Exception:
                        pages = []
                if pages:
                    self.ws = websocket.create_connection(
                        pages[0]["webSocketDebuggerUrl"], timeout=60, suppress_origin=True
                    )
                    self.send("Page.enable")
                    self.send("Runtime.enable")
                    self.send(
                        "Emulation.setDeviceMetricsOverride",
                        {
                            "width": self.width,
                            "height": self.height,
                            "deviceScaleFactor": self.scale,
                            "mobile": False,
                        },
                    )
                    return
            except Exception:
                time.sleep(0.5)
        raise RuntimeError("Chrome DevTools не поднялся")

    def send(self, method, params=None):
        self._id += 1
        self.ws.send(json.dumps({"id": self._id, "method": method, "params": params or {}}))
        while True:
            msg = json.loads(self.ws.recv())
            if msg.get("id") == self._id:
                if "error" in msg:
                    raise RuntimeError(f"{method}: {msg['error']}")
                return msg.get("result", {})

    def goto(self, url, wait=1.5, settle=800):
        self.send("Page.navigate", {"url": url})
        time.sleep(wait)
        self.js("document.fonts && document.fonts.ready ? 1 : 1")
        time.sleep(settle / 1000)

    def js(self, expression):
        res = self.send(
            "Runtime.evaluate",
            {"expression": expression, "returnByValue": True, "awaitPromise": True},
        )
        return res.get("result", {}).get("value")

    def click(self, selector, wait=0.8):
        ok = self.js(
            "(function(){var e=document.querySelector(%s); if(!e) return false; e.scrollIntoView({block:'center'}); e.click(); return true;})()"
            % json.dumps(selector)
        )
        time.sleep(wait)
        return ok

    def hover(self, selector, wait=0.8):
        ok = self.js(
            "(function(){var e=document.querySelector(%s); if(!e) return false;"
            "e.dispatchEvent(new MouseEvent('mouseover',{bubbles:true}));"
            "e.dispatchEvent(new MouseEvent('mouseenter',{bubbles:true}));"
            "e.dispatchEvent(new MouseEvent('mousemove',{bubbles:true})); return true;})()" % json.dumps(selector)
        )
        time.sleep(wait)
        return ok

    def scroll_to_bottom(self, wait=0.6):
        self.js("window.scrollTo(0, document.body.scrollHeight)")
        time.sleep(wait)

    def scroll_to(self, y, wait=0.5):
        self.js(f"window.scrollTo(0, {y})")
        time.sleep(wait)

    def screenshot(self, path, full_page=False):
        if full_page:
            height = self.js("Math.max(document.body.scrollHeight, document.documentElement.scrollHeight)")
            self.send(
                "Emulation.setDeviceMetricsOverride",
                {
                    "width": self.width,
                    "height": int(height) + 20,
                    "deviceScaleFactor": self.scale,
                    "mobile": False,
                },
            )
            time.sleep(0.6)
        res = self.send("Page.captureScreenshot", {"format": "png", "captureBeyondViewport": bool(full_page)})
        import base64

        open(path, "wb").write(base64.b64decode(res["data"]))
        if full_page:
            self.send(
                "Emulation.setDeviceMetricsOverride",
                {
                    "width": self.width,
                    "height": self.height,
                    "deviceScaleFactor": self.scale,
                    "mobile": False,
                },
            )
        return path

    def close(self):
        try:
            self.ws.close()
        except Exception:
            pass
        self.proc.terminate()
        try:
            self.proc.wait(timeout=10)
        except Exception:
            self.proc.kill()
