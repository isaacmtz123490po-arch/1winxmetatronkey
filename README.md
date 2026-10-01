<p align="center">
  <img src="banner.gif" alt="mrtatron server" width="100%">
</p>

<h1 align="center">🚀 mrtatron server</h1>

<p align="center">
  Servidor HTTP ligero con <b>Node.js + Express</b> que responde con un saludo y la IP del cliente.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/node-%3E%3D18-green?logo=node.js">
  <img src="https://img.shields.io/badge/express-4.x-black?logo=express">
  <img src="https://img.shields.io/badge/license-MIT-blue">
</p>

---

## ✨ Características

- Escucha en `0.0.0.0:3000` (accesible desde la red local)
- Responde con un saludo y la IP del cliente
- Puerto configurable con la variable `PORT`

## 📦 Instalación rápida (todo en este README)

### 1. Crea la carpeta

```bash
mkdir mi-aplicacion && cd mi-aplicacion
```

### 2. Crea `package.json`

```json
{
  "name": "mrtatron-server",
  "version": "1.0.0",
  "description": "Servidor HTTP de mrtatron server con Express",
  "main": "app.js",
  "scripts": { "start": "node app.js" },
  "dependencies": { "express": "^4.19.2" },
  "license": "MIT"
}
```

### 3. Crea `app.js`

```js
const express = require('express');
const app = express();
const PORT = process.env.PORT || 3000;
const HOST = '0.0.0.0';

app.get('/', (req, res) => {
  const ip = req.ip.replace('::ffff:', '');
  res.send(`¡Hola desde mrtatron server! Tu IP es: ${ip}`);
});

app.listen(PORT, HOST, () => {
  console.log(`mrtatron server escuchando en http://${HOST}:${PORT}`);
});
```

### 4. Instala y ejecuta

```bash
npm install
npm start
```

Otro puerto:

```bash
PORT=8080 npm start
```

## 🌐 Uso

| Acceso | URL |
|--------|-----|
| Local | `http://localhost:3000` |
| Red | `http://<tu_direccion_IP>:3000` |

Respuesta de ejemplo:

```
¡Hola desde mrtatron server! Tu IP es: 192.168.1.20
```

---

<p align="center">Hecho con ❤️ por <b>mrtatron server</b></p>
