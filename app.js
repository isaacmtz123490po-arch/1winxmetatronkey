const express = require('express');
const app = express();
const port = 8787; // Cambia el puerto si es necesario

// Ruta básica
app.get('/', (req, res) => {
    res.send('¡Hola Mundo! Tu dirección IP es ' + req.ip);
});

// Iniciando el servidor
app.listen(port, '127.0.0.1', () => {
    console.log(`Servidor escuchando en http://127.0.0.1:${port}`);
});

