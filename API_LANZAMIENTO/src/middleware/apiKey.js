const crypto = require('crypto');

// Valida el header x-api-key en TODAS las rutas
module.exports = (req, res, next) => {
  const recibida = req.header('x-api-key') || '';
  const esperada = process.env.API_KEY || '';

  const a = Buffer.from(recibida);
  const b = Buffer.from(esperada);

  if (!esperada || a.length !== b.length || !crypto.timingSafeEqual(a, b)) {
    return res.status(401).json({ error: 'API key inválida o ausente (header x-api-key)' });
  }
  next();
};
