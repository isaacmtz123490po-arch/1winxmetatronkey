#!/usr/bin/env python3
"""Servidor local para el Asistente Ollama; enlaza solo a 127.0.0.1."""
from __future__ import annotations

import ipaddress
import json
import socket
import sys
from html.parser import HTMLParser
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.error import HTTPError, URLError
from urllib.parse import parse_qs, urlsplit
from urllib.request import (HTTPRedirectHandler, Request, build_opener, urlopen)
from pathlib import Path

ROOT = Path(__file__).resolve().parent
HOST = "127.0.0.1"
PORT = 8765
MAX_BODY = 2 * 1024 * 1024
MAX_READ = 2 * 1024 * 1024


def json_bytes(value):
    return json.dumps(value, ensure_ascii=False).encode("utf-8")


def ollama_base(value):
    value = (value or "http://localhost:11434").strip().rstrip("/")
    parsed = urlsplit(value)
    if parsed.scheme not in ("http", "https") or not parsed.hostname or parsed.username or parsed.password:
        raise ValueError("La dirección de Ollama debe ser una URL http(s) válida, sin credenciales.")
    return value


def validate_public_url(value):
    parsed = urlsplit((value or "").strip())
    if parsed.scheme not in ("http", "https") or not parsed.hostname or parsed.username or parsed.password:
        raise ValueError("Usa una URL pública http(s), sin usuario ni contraseña.")
    if parsed.port not in (None, 80, 443):
        raise ValueError("Por seguridad solo se leen páginas web en puertos 80 o 443.")
    try:
        addresses = {item[4][0] for item in socket.getaddrinfo(parsed.hostname, parsed.port or (443 if parsed.scheme == "https" else 80), type=socket.SOCK_STREAM)}
    except OSError as exc:
        raise ValueError("No se pudo localizar el sitio indicado.") from exc
    if not addresses or any(not ipaddress.ip_address(address).is_global for address in addresses):
        raise ValueError("Por seguridad, solo se permiten direcciones públicas; no localhost ni redes privadas.")
    return parsed.geturl()


class SafeRedirect(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        validate_public_url(newurl)
        return super().redirect_request(req, fp, code, msg, headers, newurl)


class TextExtractor(HTMLParser):
    SKIP = {"script", "style", "noscript", "svg", "nav", "footer", "header", "iframe", "form", "button"}

    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.parts = []
        self.skip_depth = 0

    def handle_starttag(self, tag, attrs):
        if tag.lower() in self.SKIP:
            self.skip_depth += 1

    def handle_endtag(self, tag):
        if tag.lower() in self.SKIP and self.skip_depth:
            self.skip_depth -= 1

    def handle_data(self, data):
        if not self.skip_depth:
            value = " ".join(data.split())
            if value:
                self.parts.append(value)


class Handler(BaseHTTPRequestHandler):
    server_version = "AsistenteLocal/1.0"

    def log_message(self, fmt, *args):
        print("[%s] %s" % (self.log_date_time_string(), fmt % args))

    def send_json(self, status, value):
        body = json_bytes(value)
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "no-store")
        self.end_headers()
        self.wfile.write(body)

    def read_json(self):
        try:
            size = int(self.headers.get("Content-Length", "0"))
        except ValueError as exc:
            raise ValueError("Longitud de solicitud no válida.") from exc
        if size < 0 or size > MAX_BODY:
            raise ValueError("La solicitud es demasiado grande.")
        raw = self.rfile.read(size)
        try:
            value = json.loads(raw.decode("utf-8"))
        except (UnicodeDecodeError, json.JSONDecodeError) as exc:
            raise ValueError("El cuerpo de la solicitud no es JSON válido.") from exc
        if not isinstance(value, dict):
            raise ValueError("Se esperaba un objeto JSON.")
        return value

    def do_GET(self):
        parsed = urlsplit(self.path)
        if parsed.path in ("/", "/index.html"):
            try:
                body = (ROOT / "index.html").read_bytes()
                self.send_response(200)
                self.send_header("Content-Type", "text/html; charset=utf-8")
                self.send_header("Content-Length", str(len(body)))
                self.send_header("Cache-Control", "no-store")
                self.end_headers()
                self.wfile.write(body)
            except OSError:
                self.send_json(500, {"error": "No se encontró index.html junto a server.py."})
            return
        if parsed.path == "/api/tags":
            params = parse_qs(parsed.query)
            try:
                base = ollama_base(params.get("endpoint", [""])[0])
                self.proxy_ollama(base + "/api/tags", None, 15)
            except ValueError as exc:
                self.send_json(400, {"error": str(exc)})
            return
        self.send_json(404, {"error": "Ruta no encontrada."})

    def do_POST(self):
        path = urlsplit(self.path).path
        try:
            data = self.read_json()
            if path == "/api/chat":
                base = ollama_base(data.get("endpoint", ""))
                model = data.get("model")
                messages = data.get("messages")
                if not isinstance(model, str) or not model.strip() or not isinstance(messages, list):
                    raise ValueError("Selecciona un modelo y envía mensajes válidos.")
                payload = {"model": model, "messages": messages, "stream": False}
                self.proxy_ollama(base + "/api/chat", payload, 180)
                return
            if path == "/api/search":
                self.search(data.get("query", ""))
                return
            if path == "/api/read":
                self.read_page(data.get("url", ""))
                return
            self.send_json(404, {"error": "Ruta no encontrada."})
        except ValueError as exc:
            self.send_json(400, {"error": str(exc)})
        except Exception as exc:
            self.send_json(500, {"error": "Ocurrió un error local: " + str(exc)[:300]})

    def proxy_ollama(self, target, payload, timeout):
        body = json_bytes(payload) if payload is not None else None
        req = Request(target, data=body, headers={"Content-Type": "application/json", "Accept": "application/json"}, method="POST" if body is not None else "GET")
        try:
            with urlopen(req, timeout=timeout) as response:
                raw = response.read(MAX_BODY)
                code = response.status
        except HTTPError as exc:
            raw = exc.read(MAX_BODY)
            code = exc.code
        except (URLError, TimeoutError, OSError) as exc:
            reason = getattr(exc, "reason", exc)
            self.send_json(502, {"error": "No se pudo conectar con Ollama: " + str(reason)[:240]})
            return
        self.send_response(code)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(raw)))
        self.send_header("Cache-Control", "no-store")
        self.end_headers()
        self.wfile.write(raw)

    def search(self, query):
        if not isinstance(query, str) or not query.strip():
            raise ValueError("Escribe una consulta para buscar.")
        query = query.strip()[:500]
        try:
            from ddgs import DDGS
        except ImportError as exc:
            self.send_json(503, {"error": "Falta instalar el buscador. Ejecuta el iniciador del paquete o instala requirements.txt."})
            return
        try:
            raw = list(DDGS().text(query, max_results=8))
        except Exception as exc:
            self.send_json(502, {"error": "El buscador no respondió. Intenta de nuevo más tarde. " + str(exc)[:180]})
            return
        results = []
        for item in raw:
            url = item.get("href") or item.get("url") or ""
            if not isinstance(url, str) or urlsplit(url).scheme not in ("http", "https"):
                continue
            results.append({
                "title": str(item.get("title") or url)[:300],
                "url": url[:2000],
                "snippet": str(item.get("body") or item.get("snippet") or "")[:1200],
            })
        self.send_json(200, {"results": results})

    def read_page(self, value):
        url = validate_public_url(value)
        opener = build_opener(SafeRedirect())
        req = Request(url, headers={"User-Agent": "AsistenteLocal/1.0 (public page reader)", "Accept": "text/html,application/xhtml+xml"})
        try:
            with opener.open(req, timeout=15) as response:
                content_type = response.headers.get_content_type()
                if content_type not in ("text/html", "application/xhtml+xml"):
                    raise ValueError("La URL no parece ser una página HTML pública.")
                raw = response.read(MAX_READ)
                charset = response.headers.get_content_charset() or "utf-8"
                final_url = response.geturl()
        except HTTPError as exc:
            raise ValueError("El sitio rechazó la lectura (HTTP %s)." % exc.code) from exc
        except (URLError, TimeoutError, OSError) as exc:
            raise ValueError("No se pudo leer el sitio; puede bloquear solicitudes automatizadas.") from exc
        validate_public_url(final_url)
        try:
            html = raw.decode(charset, errors="replace")
        except LookupError:
            html = raw.decode("utf-8", errors="replace")
        parser = TextExtractor()
        parser.feed(html)
        text = " ".join(parser.parts)[:10000]
        if len(text) < 40:
            raise ValueError("No se encontró suficiente texto legible en esa página.")
        self.send_json(200, {"url": final_url, "text": text})


def main():
    try:
        server = ThreadingHTTPServer((HOST, PORT), Handler)
    except OSError as exc:
        print("No pude iniciar el servidor en http://%s:%s: %s" % (HOST, PORT, exc), file=sys.stderr)
        print("Cierra otra app que use ese puerto e inténtalo de nuevo.", file=sys.stderr)
        raise SystemExit(1)
    print("Asistente listo: http://%s:%s" % (HOST, PORT))
    print("Deja esta ventana abierta. Para detenerlo, pulsa Ctrl+C.")
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\nServidor detenido.")
    finally:
        server.server_close()


if __name__ == "__main__":
    main()
