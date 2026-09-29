import os
import re
import uuid
import json
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

UPLOAD_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "uploads")
os.makedirs(UPLOAD_DIR, exist_ok=True)

# Cambia esta clave por una que solo tu conozcas antes de usar /actualizar
CLAVE_ACTUALIZACION = "TU_CLAVE_AQUI"

PAGINA_ACTUALIZAR = """<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Actualizar app.py</title>
  <style>
    body { font-family: sans-serif; background:#111; color:#eee; text-align:center; padding-top:60px; }
    input, button { font-size:18px; margin:10px; padding:10px; }
    button { background:#e94b3c; color:white; border:none; border-radius:6px; }
    #resultado { margin-top:20px; word-break:break-all; }
  </style>
</head>
<body>
  <h2>Reemplazar app.py</h2>
  <form id="form">
    <input type="password" name="clave" id="clave" placeholder="Clave" required><br>
    <input type="file" name="file" id="file" required><br>
    <button type="submit">Reemplazar</button>
  </form>
  <div id="resultado"></div>
  <script>
    document.getElementById("form").addEventListener("submit", async function(e) {
      e.preventDefault();
      const fileInput = document.getElementById("file");
      const clave = document.getElementById("clave").value;
      const resultado = document.getElementById("resultado");
      if (!fileInput.files.length) return;
      const formData = new FormData();
      formData.append("file", fileInput.files[0]);
      formData.append("clave", clave);
      resultado.innerText = "Actualizando...";
      try {
        const resp = await fetch("/actualizar", { method: "POST", body: formData });
        const data = await resp.json();
        resultado.innerText = resp.ok
          ? "Listo. Cierra y vuelve a abrir la app para cargar la nueva version."
          : "Error: " + (data.error || "desconocido");
      } catch (err) {
        resultado.innerText = "Error de conexion: " + err;
      }
    });
  </script>
</body>
</html>"""

PAGINA_SUBIDA = """<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Subir archivo</title>
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background: linear-gradient(160deg, #0f0f14 0%, #1a1a24 100%);
      color: #eee;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }
    .card {
      width: 100%;
      max-width: 420px;
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 20px;
      padding: 32px 24px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.4);
    }
    h2 {
      margin: 0 0 24px;
      font-size: 22px;
      font-weight: 600;
      text-align: center;
    }
    .drop {
      border: 2px dashed rgba(255,255,255,0.15);
      border-radius: 14px;
      padding: 28px 16px;
      text-align: center;
      cursor: pointer;
      transition: border-color .2s, background .2s;
    }
    .drop:hover, .drop.dragover {
      border-color: #7c6cf6;
      background: rgba(124,108,246,0.06);
    }
    .drop input { display: none; }
    .drop-label { font-size: 15px; color: #aaa; }
    .drop-label b { color: #eee; }
    #filename { margin-top: 10px; font-size: 13px; color: #7c6cf6; word-break: break-all; }
    .progreso-wrap {
      margin-top: 16px;
      display: none;
    }
    .progreso-bar {
      width: 100%;
      height: 8px;
      background: rgba(255,255,255,0.08);
      border-radius: 999px;
      overflow: hidden;
    }
    .progreso-fill {
      height: 100%;
      width: 0%;
      background: linear-gradient(90deg, #7c6cf6, #5b4ce0);
      transition: width .15s ease;
    }
    .progreso-pct { margin-top: 6px; font-size: 12px; color: #aaa; text-align: center; }
    button.subir {
      width: 100%;
      margin-top: 20px;
      padding: 14px;
      font-size: 16px;
      font-weight: 600;
      color: white;
      background: linear-gradient(135deg, #7c6cf6, #5b4ce0);
      border: none;
      border-radius: 12px;
      cursor: pointer;
      transition: transform .15s, opacity .15s;
    }
    button.subir:active { transform: scale(0.98); }
    button.subir:disabled { opacity: 0.5; }
    #resultado {
      margin-top: 20px;
      display: none;
    }
    .url-box {
      display: flex;
      align-items: center;
      gap: 8px;
      background: rgba(255,255,255,0.06);
      border-radius: 10px;
      padding: 10px 12px;
    }
    .url-box input {
      flex: 1;
      background: transparent;
      border: none;
      color: #eee;
      font-size: 13px;
      outline: none;
    }
    .copy-btn {
      flex-shrink: 0;
      padding: 8px 14px;
      font-size: 13px;
      font-weight: 600;
      color: white;
      background: #2f2f3d;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      transition: background .15s;
    }
    .copy-btn:hover { background: #3d3d4f; }
    .copy-btn.copied { background: #2fa86a; }
    .estado { margin-top: 12px; font-size: 13px; color: #aaa; text-align: center; }
    .error { color: #ff6b6b; }
  </style>
</head>
<body>
  <div class="card">
    <h2>Subir archivo</h2>
    <label class="drop" id="drop">
      <input type="file" id="file">
      <div class="drop-label">Toca para elegir un archivo<br><b id="drop-text"></b></div>
      <div id="filename"></div>
    </label>
    <button class="subir" id="btnSubir" disabled>Subir archivo</button>
    <div class="progreso-wrap" id="progresoWrap">
      <div class="progreso-bar"><div class="progreso-fill" id="progresoFill"></div></div>
      <div class="progreso-pct" id="progresoPct">0%</div>
    </div>
    <div id="resultado">
      <div class="url-box">
        <input type="text" id="urlOut" readonly>
        <button class="copy-btn" id="btnCopiar">Copiar</button>
      </div>
    </div>
    <div class="estado" id="estado"></div>
  </div>

  <script>
    const fileInput = document.getElementById("file");
    const filenameEl = document.getElementById("filename");
    const btnSubir = document.getElementById("btnSubir");
    const resultado = document.getElementById("resultado");
    const urlOut = document.getElementById("urlOut");
    const btnCopiar = document.getElementById("btnCopiar");
    const estado = document.getElementById("estado");
    const progresoWrap = document.getElementById("progresoWrap");
    const progresoFill = document.getElementById("progresoFill");
    const progresoPct = document.getElementById("progresoPct");

    fileInput.addEventListener("change", () => {
      if (fileInput.files.length) {
        filenameEl.textContent = fileInput.files[0].name;
        btnSubir.disabled = false;
      }
    });

    function subirConProgreso(file) {
      return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        const formData = new FormData();
        formData.append("file", file);

        xhr.upload.addEventListener("progress", (e) => {
          if (e.lengthComputable) {
            const pct = Math.round((e.loaded / e.total) * 100);
            progresoFill.style.width = pct + "%";
            progresoPct.textContent = pct + "% (" + formatBytes(e.loaded) + " / " + formatBytes(e.total) + ")";
          }
        });

        xhr.onreadystatechange = () => {
          if (xhr.readyState === 4) {
            try {
              const data = JSON.parse(xhr.responseText);
              if (xhr.status >= 200 && xhr.status < 300) {
                resolve(data);
              } else {
                reject(data.error || "Error desconocido");
              }
            } catch (e) {
              reject("Respuesta invalida del servidor");
            }
          }
        };

        xhr.onerror = () => reject("Error de conexion (se corto la subida)");
        xhr.open("POST", "/upload");
        xhr.send(formData);
      });
    }

    function formatBytes(n) {
      if (n < 1024) return n + " B";
      if (n < 1024 * 1024) return (n / 1024).toFixed(1) + " KB";
      return (n / (1024 * 1024)).toFixed(1) + " MB";
    }

    btnSubir.addEventListener("click", async () => {
      if (!fileInput.files.length) return;
      btnSubir.disabled = true;
      estado.textContent = "";
      estado.className = "estado";
      resultado.style.display = "none";
      progresoWrap.style.display = "block";
      progresoFill.style.width = "0%";
      progresoPct.textContent = "0%";

      try {
        const data = await subirConProgreso(fileInput.files[0]);
        urlOut.value = data.url_descarga;
        resultado.style.display = "block";
        estado.textContent = "Listo, archivo verificado integro";
        btnCopiar.textContent = "Copiar";
        btnCopiar.classList.remove("copied");
      } catch (err) {
        estado.textContent = "Error: " + err;
        estado.className = "estado error";
        progresoWrap.style.display = "none";
      }
      btnSubir.disabled = false;
    });

    btnCopiar.addEventListener("click", async () => {
      try {
        await navigator.clipboard.writeText(urlOut.value);
      } catch (e) {
        urlOut.select();
        document.execCommand("copy");
      }
      btnCopiar.textContent = "Copiado";
      btnCopiar.classList.add("copied");
      setTimeout(() => {
        btnCopiar.textContent = "Copiar";
        btnCopiar.classList.remove("copied");
      }, 1500);
    });
  </script>
</body>
</html>"""


class Handler(BaseHTTPRequestHandler):
    def _send_json(self, status, data):
        body = json.dumps(data).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        if self.path == "/" or self.path == "":
            body = PAGINA_SUBIDA.encode("utf-8")
            self.send_response(200)
            self.send_header("Content-Type", "text/html; charset=utf-8")
            self.send_header("Content-Length", str(len(body)))
            self.end_headers()
            self.wfile.write(body)
            return

        if self.path.startswith("/files/"):
            safe_name = os.path.basename(self.path[len("/files/"):])
            file_path = os.path.join(UPLOAD_DIR, safe_name)
            if not os.path.isfile(file_path):
                self.send_response(404)
                self.end_headers()
                self.wfile.write(b"No encontrado")
                return
            self.send_response(200)
            self.send_header("Content-Type", "application/octet-stream")
            self.send_header("Content-Disposition", f'attachment; filename="{safe_name}"')
            self.send_header("Content-Length", str(os.path.getsize(file_path)))
            self.end_headers()
            with open(file_path, "rb") as f:
                self.wfile.write(f.read())
            return

        if self.path == "/actualizar":
            body = PAGINA_ACTUALIZAR.encode("utf-8")
            self.send_response(200)
            self.send_header("Content-Type", "text/html; charset=utf-8")
            self.send_header("Content-Length", str(len(body)))
            self.end_headers()
            self.wfile.write(body)
            return

        self.send_response(404)
        self.end_headers()
        self.wfile.write(b"No encontrado")

    def _leer_multipart(self):
        """Lee un POST multipart/form-data y devuelve (campos_texto, filename, file_bytes)."""
        content_type = self.headers.get("Content-Type", "")
        if "multipart/form-data" not in content_type:
            return None, None, None, "Se esperaba multipart/form-data"

        m = re.search(r'boundary=(?:"([^"]+)"|([^;]+))', content_type)
        if not m:
            return None, None, None, "No se encontro boundary"
        boundary = (m.group(1) or m.group(2)).strip()
        boundary_bytes = ("--" + boundary).encode("utf-8")

        length = int(self.headers.get("Content-Length", 0))
        raw = self.rfile.read(length)

        parts = raw.split(boundary_bytes)
        campos = {}
        filename = None
        file_bytes = None

        for part in parts:
            # Quita SOLO el CRLF inicial que sigue al boundary (recorte exacto,
            # no un strip() de caracteres, que borraria bytes reales del archivo
            # si este terminaba en \r o \n).
            if part.startswith(b"\r\n"):
                part = part[2:]
            if not part or part in (b"--", b"--\r\n"):
                continue
            if b"\r\n\r\n" not in part:
                continue
            headers_blob, content = part.split(b"\r\n\r\n", 1)
            headers_text = headers_blob.decode("utf-8", errors="replace")
            if content.endswith(b"\r\n"):
                content = content[:-2]

            nm = re.search(r'name="([^"]*)"', headers_text)
            if not nm:
                continue
            field_name = nm.group(1)

            fm = re.search(r'filename="([^"]*)"', headers_text)
            if fm and fm.group(1):
                filename = fm.group(1)
                file_bytes = content
            else:
                campos[field_name] = content.decode("utf-8", errors="replace")

        return campos, filename, file_bytes, None

    def do_POST(self):
        if self.path == "/actualizar":
            campos, filename, file_bytes, err = self._leer_multipart()
            if err:
                self._send_json(400, {"error": err})
                return
            if campos.get("clave") != CLAVE_ACTUALIZACION:
                self._send_json(403, {"error": "Clave incorrecta"})
                return
            if not filename:
                self._send_json(400, {"error": "No se envio ningun archivo"})
                return

            destino = os.path.abspath(__file__)
            with open(destino, "wb") as f:
                f.write(file_bytes)

            self._send_json(200, {"mensaje": "app.py reemplazado. Reinicia la app manualmente."})
            return

        if self.path != "/upload":
            self.send_response(404)
            self.end_headers()
            return

        content_type = self.headers.get("Content-Type", "")
        if "multipart/form-data" not in content_type:
            self._send_json(400, {"error": "Se esperaba multipart/form-data"})
            return

        m = re.search(r'boundary=(?:"([^"]+)"|([^;]+))', content_type)
        if not m:
            self._send_json(400, {"error": "No se encontro boundary"})
            return
        boundary = (m.group(1) or m.group(2)).strip()
        boundary_bytes = ("--" + boundary).encode("utf-8")

        length = int(self.headers.get("Content-Length", 0))
        raw = self.rfile.read(length)

        # Verificacion de integridad: si la conexion se corto a medias,
        # no llegan todos los bytes esperados y se rechaza en vez de guardar
        # un archivo corrupto/incompleto.
        if len(raw) != length:
            self._send_json(400, {
                "error": f"Conexion interrumpida: se esperaban {length} bytes, llegaron {len(raw)}"
            })
            return

        parts = raw.split(boundary_bytes)
        filename = None
        file_bytes = None

        for part in parts:
            # Recorte exacto del CRLF inicial (no strip() de caracteres,
            # que corrompe archivos terminados en \r o \n)
            if part.startswith(b"\r\n"):
                part = part[2:]
            if not part or part in (b"--", b"--\r\n"):
                continue
            if b"\r\n\r\n" not in part:
                continue
            headers_blob, content = part.split(b"\r\n\r\n", 1)
            headers_text = headers_blob.decode("utf-8", errors="replace")
            if 'name="file"' not in headers_text:
                continue
            fm = re.search(r'filename="([^"]*)"', headers_text)
            if not fm:
                continue
            filename = fm.group(1)
            # quita el CRLF final que separa el contenido del siguiente boundary
            if content.endswith(b"\r\n"):
                content = content[:-2]
            file_bytes = content
            break

        if not filename:
            self._send_json(400, {"error": "No se envio ningun archivo (campo 'file')"})
            return

        file_id = uuid.uuid4().hex
        original_name = os.path.basename(filename)
        stored_name = f"{file_id}_{original_name}"
        save_path = os.path.join(UPLOAD_DIR, stored_name)
        tmp_path = save_path + ".part"

        # Escribe primero a un archivo temporal y solo lo renombra al final;
        # asi, si algo falla a medias, nunca queda un archivo final corrupto.
        with open(tmp_path, "wb") as f:
            f.write(file_bytes)
        os.replace(tmp_path, save_path)

        host = self.headers.get("Host", "localhost:5000")
        download_url = f"http://{host}/files/{stored_name}"

        self._send_json(201, {
            "mensaje": "Archivo subido correctamente",
            "url_descarga": download_url
        })

    def log_message(self, format, *args):
        pass


if __name__ == "__main__":
    port = int(os.environ.get("PORT", 5000))
    server = ThreadingHTTPServer(("0.0.0.0", port), Handler)
    print(f"Servidor corriendo en http://0.0.0.0:{port}")
    server.serve_forever()
