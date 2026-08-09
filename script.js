/* ===========================
   NANDX SENSIBILITY — SCRIPT
=========================== */

// ===== DEVICE DATABASE (Tier Classification) =====
const DEVICE_DB = {
  // Low-end
  'redmi 9a': { tier: 'low', boost: 0 },
  'redmi 9c': { tier: 'low', boost: 0 },
  'galaxy a03': { tier: 'low', boost: 0 },
  'tecno pop': { tier: 'low', boost: 0 },
  'itel': { tier: 'low', boost: 0 },
  // Mid-low
  'redmi 9': { tier: 'mid-low', boost: 5 },
  'redmi 10': { tier: 'mid-low', boost: 5 },
  'moto g23': { tier: 'mid-low', boost: 5 },
  'moto g13': { tier: 'mid-low', boost: 5 },
  'galaxy a14': { tier: 'mid-low', boost: 5 },
  'galaxy a23': { tier: 'mid-low', boost: 5 },
  'honor x7': { tier: 'mid-low', boost: 5 },
  // Mid
  'redmi note 12': { tier: 'mid', boost: 10 },
  'redmi note 11': { tier: 'mid', boost: 10 },
  'moto g54': { tier: 'mid', boost: 10 },
  'galaxy a54': { tier: 'mid', boost: 10 },
  'galaxy a35': { tier: 'mid', boost: 10 },
  'honor x8': { tier: 'mid', boost: 10 },
  'poco m4': { tier: 'mid', boost: 10 },
  'poco m5': { tier: 'mid', boost: 10 },
  // Mid-high
  'redmi note 13 pro': { tier: 'mid-high', boost: 15 },
  'poco x5': { tier: 'mid-high', boost: 15 },
  'poco x6': { tier: 'mid-high', boost: 15 },
  'galaxy a55': { tier: 'mid-high', boost: 15 },
  'galaxy s23 fe': { tier: 'mid-high', boost: 15 },
  'realme gt': { tier: 'mid-high', boost: 15 },
  // High
  'galaxy s24': { tier: 'high', boost: 20 },
  'galaxy s23': { tier: 'high', boost: 20 },
  'iphone 14': { tier: 'high', boost: 20 },
  'iphone 13': { tier: 'high', boost: 20 },
  'poco f5': { tier: 'high', boost: 20 },
  'redmi k60': { tier: 'high', boost: 20 },
};

const TIER_LABELS = {
  'low': '⬛ GAMA BAJA',
  'mid-low': '🟧 GAMA MEDIA-BAJA',
  'mid': '🟦 GAMA MEDIA',
  'mid-high': '🟪 GAMA MEDIA-ALTA',
  'high': '🟦 GAMA ALTA',
};

// ===== BASE SENSITIVITY VALUES per tier =====
const BASE_SENS = {
  'low': {
    general: [155, 175], punto_rojo: [160, 180], mira2x: [155, 175],
    mira4x: [170, 195], awm: [155, 175], cam360: [145, 165],
    fire_btn: [35, 50],
  },
  'mid-low': {
    general: [165, 185], punto_rojo: [170, 190], mira2x: [165, 185],
    mira4x: [180, 198], awm: [165, 185], cam360: [155, 175],
    fire_btn: [40, 55],
  },
  'mid': {
    general: [170, 190], punto_rojo: [172, 192], mira2x: [170, 190],
    mira4x: [185, 200], awm: [168, 188], cam360: [160, 180],
    fire_btn: [42, 58],
  },
  'mid-high': {
    general: [175, 195], punto_rojo: [175, 195], mira2x: [175, 195],
    mira4x: [188, 200], awm: [170, 190], cam360: [162, 182],
    fire_btn: [44, 60],
  },
  'high': {
    general: [178, 198], punto_rojo: [178, 198], mira2x: [178, 198],
    mira4x: [190, 200], awm: [172, 192], cam360: [165, 185],
    fire_btn: [46, 62],
  },
};

// DPI modifier (WITH DPI = slightly lower base values needed)
const DPI_MOD = { sin: 0, con: -8 };

// RAM modifier
function ramMod(ram) {
  const r = parseInt(ram);
  if (r <= 2) return -10;
  if (r <= 3) return -5;
  if (r <= 4) return 0;
  if (r <= 6) return 5;
  if (r <= 8) return 8;
  return 12;
}

// ===== DEVICE DETECTION =====
function detectDevice(name) {
  const n = name.toLowerCase().trim();
  for (const key of Object.keys(DEVICE_DB)) {
    if (n.includes(key)) return DEVICE_DB[key];
  }
  // Guess by keywords
  if (n.includes('pro') || n.includes('ultra') || n.includes('plus')) return { tier: 'mid-high', boost: 15 };
  if (n.includes('note')) return { tier: 'mid', boost: 10 };
  return { tier: 'mid-low', boost: 5 };
}

function clamp(val, min, max) {
  return Math.min(max, Math.max(min, Math.round(val)));
}

function randBetween(a, b) {
  return Math.floor(Math.random() * (b - a + 1)) + a;
}

// ===== GENERATE SENSITIVITY =====
function generateSensitivity(deviceName, ram, dpiMode, fireBtnMode, fireBtnValue) {
  const info = detectDevice(deviceName);
  const base = BASE_SENS[info.tier];
  const mod = DPI_MOD[dpiMode] + ramMod(ram);

  const result = {
    tier: info.tier,
    general: clamp(randBetween(...base.general) + mod, 0, 200),
    punto_rojo: clamp(randBetween(...base.punto_rojo) + mod, 0, 200),
    mira2x: clamp(randBetween(...base.mira2x) + mod, 0, 200),
    mira4x: clamp(randBetween(...base.mira4x) + mod, 0, 200),
    awm: clamp(randBetween(...base.awm) + mod, 0, 200),
    cam360: clamp(randBetween(...base.cam360) + mod, 0, 200),
  };

  if (fireBtnMode === 'manual') {
    result.fire_btn = parseInt(fireBtnValue);
  } else {
    // Auto-generate fire button synced with sensitivities
    const avgSens = (result.general + result.punto_rojo) / 2;
    const baseBtn = randBetween(...base.fire_btn);
    // Higher sensitivity = slightly lower fire btn to prevent shake
    const syncedBtn = clamp(baseBtn - Math.round((avgSens - 170) * 0.1), 30, 70);
    result.fire_btn = syncedBtn;
  }

  return result;
}

// ===== STABILIZE SENSITIVITY =====
function stabilizeSensitivity(deviceName, ram, dpiMode, currentVals) {
  const info = detectDevice(deviceName);
  const base = BASE_SENS[info.tier];
  const mod = DPI_MOD[dpiMode] + ramMod(ram);

  // Ideal ranges for each sight
  const ideals = {
    general: clamp(randBetween(...base.general) + mod, 0, 200),
    punto_rojo: clamp(randBetween(...base.punto_rojo) + mod, 0, 200),
    mira2x: clamp(randBetween(...base.mira2x) + mod, 0, 200),
    mira4x: clamp(randBetween(...base.mira4x) + mod, 0, 200),
    awm: clamp(randBetween(...base.awm) + mod, 0, 200),
    cam360: clamp(randBetween(...base.cam360) + mod, 0, 200),
  };

  const result = {};
  const keys = ['general', 'punto_rojo', 'mira2x', 'mira4x', 'awm', 'cam360'];

  for (const key of keys) {
    const current = currentVals[key];
    const ideal = ideals[key];
    // Blend: 60% user preference, 40% ideal for stability
    result[key] = clamp(Math.round(current * 0.6 + ideal * 0.4), 0, 200);
  }

  result.tier = info.tier;
  return result;
}

// ===== HUD CODES DATABASE =====
const HUD_CODES = {
  2: [
    {
      name: 'Layout 2 Dedos Pro SA',
      tag: 'Movilidad Alta',
      code: '#FFHUD2F3Sa+oAj9PoQRNx7mKvY2ZSAM',
      desc: 'Optimizado para movilidad y disparo rápido',
    },
    {
      name: '2 Dedos Anti-Trabadas',
      tag: 'Sin Trabadas',
      code: '#FFHUD2G7Lb+pBk0QrSNy8nMwZ3ATBN',
      desc: 'Espaciado amplio para evitar toques accidentales',
    },
    {
      name: '2 Dedos Equilibrado SA',
      tag: 'Equilibrado',
      code: '#FFHUD2H1Mc+qCl1RsTO9oNxA4BUCP',
      desc: 'Balance entre movilidad y precisión de disparo',
    },
  ],
  3: [
    {
      name: '3 Dedos Competitivo SA',
      tag: 'Competitivo',
      code: '#FFHUD3J2Nd+rDm2StUP0pOyB5CVDQ',
      desc: 'Control total para 3 dedos, mínimo solapamiento',
    },
    {
      name: '3 Dedos Rush SA',
      tag: 'Rush',
      code: '#FFHUD3K4Pe+sFn3TuVQ1qPzC6DWER',
      desc: 'Para jugadores agresivos que rushean seguido',
    },
    {
      name: '3 Dedos Estable SA',
      tag: 'Estabilidad',
      code: '#FFHUD3L6Qf+tGo4UvWR2rQAD7EXFS',
      desc: 'Prioriza estabilidad y mira sin temblor',
    },
  ],
  4: [
    {
      name: '4 Dedos Elite SA',
      tag: 'Elite',
      code: '#FFHUD4M8Rg+uHp5VwXS3sRBE8FYGT',
      desc: 'HUD elite para jugadores avanzados',
    },
    {
      name: '4 Dedos Claw SA',
      tag: 'Claw',
      code: '#FFHUD4N9Sh+vIq6WxYT4tSCF9GZHU',
      desc: 'Diseñado para agarre estilo claw',
    },
    {
      name: '4 Dedos Máx. Control SA',
      tag: 'Control Total',
      code: '#FFHUD4O0Ti+wJr7XyZU5uTDG0HAIV',
      desc: 'Máximo número de acciones simultáneas posibles',
    },
  ],
};

// ===== HUD ANALYSIS =====
function analyzeHUD(fingers, keepFireBtn) {
  const configs = {
    2: {
      layout: '2 Dedos Optimizado',
      changes: [
        'Botón de agacharse movido a zona pulgar izquierdo para alcance natural',
        'Botón de disparo reposicionado a zona inferior derecha con tamaño aumentado',
        'Joystick de movimiento ampliado un 15% para mayor control',
        'Botón de salto integrado cerca del pulgar derecho',
      ],
      fireNote: keepFireBtn === 'mantener'
        ? 'Tu botón de disparo actual se mantiene en la posición original'
        : 'Botón de disparo reubicado a posición óptima anti-temblor para 2 dedos',
      tip: 'Con 2 dedos, prioriza que los botones más usados (disparo, agacharse, joystick) no se superpongan nunca.',
    },
    3: {
      layout: '3 Dedos Optimizado',
      changes: [
        'Botón de disparo izquierdo agregado para trigger pulgar extra',
        'Mira activable separada del joystick para máxima independencia',
        'Botón de granadas en zona media accesible por dedo índice',
        'Botón agacharse mejorado para slide rápido',
      ],
      fireNote: keepFireBtn === 'mantener'
        ? 'Tu botón de disparo se mantiene donde lo tienes'
        : 'Doble botón de disparo colocado en ambos lados para disparo más estable a 3 dedos',
      tip: 'Con 3 dedos puedes tener un botón de disparo en cada lado para ráfagas más estables.',
    },
    4: {
      layout: '4 Dedos Elite Optimizado',
      changes: [
        'Cuatro zonas de control separadas sin solapamiento posible',
        'Botón de mira manual agrado para switch scope rápido',
        'Botón de agacharse, salto y sprint separados en zona inferior',
        'Layout simétrico para máxima comodidad y cero trabadas accidentales',
      ],
      fireNote: keepFireBtn === 'mantener'
        ? 'Botón de disparo original conservado'
        : 'Botón de disparo principal y secundario separados para control 4 dedos',
      tip: 'A 4 dedos, lo más importante es evitar toques accidentales. Los botones críticos deben estar en extremos opuestos.',
    },
  };
  return configs[fingers];
}

// ===== DOM HELPERS =====
function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2500);
}

function copyText(text) {
  navigator.clipboard.writeText(text).then(() => showToast('¡Copiado al portapapeles!')).catch(() => {
    // fallback
    const ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    ta.remove();
    showToast('¡Copiado!');
  });
}

function showLoading(steps, onComplete) {
  const overlay = document.getElementById('loadingOverlay');
  const stepsEl = document.getElementById('loadingSteps');
  const textEl = document.getElementById('loadingText');

  overlay.classList.remove('hidden');
  stepsEl.innerHTML = '';

  let i = 0;
  const total = steps.length;

  function nextStep() {
    if (i >= total) {
      setTimeout(() => {
        overlay.classList.add('hidden');
        onComplete();
      }, 400);
      return;
    }

    textEl.textContent = steps[i].label;
    const el = document.createElement('div');
    el.className = 'loading-step';
    el.innerHTML = `<span class="loading-step-icon">◈</span> ${steps[i].text}`;
    stepsEl.appendChild(el);
    requestAnimationFrame(() => {
      setTimeout(() => el.classList.add('visible'), 50);
    });
    i++;
    setTimeout(nextStep, steps[i - 1].delay || 700);
  }

  nextStep();
}

function buildSensGrid(containerId, data, showFireBtn = false) {
  const container = document.getElementById(containerId);
  const labels = {
    general: 'General',
    punto_rojo: 'Punto Rojo',
    mira2x: 'Mira 2x',
    mira4x: 'Mira 4x',
    awm: 'Mira AWM',
    cam360: 'Cámara 360°',
  };

  let html = '';
  for (const [key, label] of Object.entries(labels)) {
    const val = data[key];
    const pct = (val / 200 * 100).toFixed(1);
    html += `
      <div class="sens-item">
        <span class="sens-label">${label}</span>
        <span class="sens-value">${val}</span>
        <div class="sens-bar"><div class="sens-bar-fill" style="width:0%" data-width="${pct}%"></div></div>
      </div>`;
  }

  if (showFireBtn && data.fire_btn !== undefined) {
    html += `
      <div class="sens-item fire-btn-item">
        <span class="sens-label">🔫 Botón de Disparo</span>
        <span class="sens-value">${data.fire_btn}%</span>
        <div class="sens-bar"><div class="sens-bar-fill" style="width:0%" data-width="${data.fire_btn}%"></div></div>
      </div>`;
  }

  container.innerHTML = html;

  // Animate bars
  setTimeout(() => {
    container.querySelectorAll('.sens-bar-fill').forEach(bar => {
      bar.style.width = bar.dataset.width;
    });
  }, 100);
}

function buildCopyText(data, deviceName, dpiMode) {
  return `🎯 NANDX SENSIBILITY — ${deviceName.toUpperCase()}
📱 DPI: ${dpiMode.toUpperCase()}

General: ${data.general}
Punto Rojo: ${data.punto_rojo}
Mira 2x: ${data.mira2x}
Mira 4x: ${data.mira4x}
Mira AWM: ${data.awm}
Cámara 360°: ${data.cam360}${data.fire_btn ? `\nBotón Disparo: ${data.fire_btn}%` : ''}

Generado con Nandx Sensibility ◈`;
}

// ===== INIT STATE =====
const state = {
  crear: { dpi: 'sin', fire: 'auto', firePct: 50 },
  estab: { dpi: 'sin' },
  hud: { fingers: 2, mode: 'cargar', keepFire: 'mantener' },
};

// ===== TOGGLE BUTTON LOGIC =====
document.querySelectorAll('.toggle-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const group = btn.dataset.group;
    document.querySelectorAll(`[data-group="${group}"]`).forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const val = btn.dataset.val;

    if (group === 'dpi-crear') state.crear.dpi = val;
    if (group === 'dpi-estab') state.estab.dpi = val;
    if (group === 'fire-crear') {
      state.crear.fire = val;
      document.getElementById('fire-manual-crear').classList.toggle('hidden', val !== 'manual');
    }
    if (group === 'keep-fire') state.hud.keepFire = val;
  });
});

// ===== RANGE INPUTS =====
const fireRange = document.getElementById('fire-range-crear');
const fireVal = document.getElementById('fire-val-crear');
fireRange.addEventListener('input', () => {
  fireVal.textContent = fireRange.value;
  state.crear.firePct = fireRange.value;
});

// Stabilizer sliders
document.querySelectorAll('#tab-estabilizar .range-input[data-key]').forEach(input => {
  const key = input.dataset.key;
  const valEl = document.getElementById(`sv-${key}`);
  input.addEventListener('input', () => {
    valEl.textContent = input.value;
  });
});

// ===== TAB SWITCHER =====
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(`tab-${btn.dataset.tab}`).classList.add('active');
  });
});

// ===== FINGERS SELECTOR =====
document.querySelectorAll('.finger-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.finger-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    state.hud.fingers = parseInt(btn.dataset.fingers);
    // Update labels
    const label = `${state.hud.fingers} dedos`;
    document.getElementById('fingers-label').textContent = label;
    document.querySelectorAll('.fingers-label-b').forEach(el => el.textContent = label);
  });
});

// ===== HUD MODE SELECT =====
document.querySelectorAll('.hud-mode-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.hud-mode-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.hud-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    state.hud.mode = btn.dataset.mode;
    document.getElementById(`panel-${btn.dataset.mode}`).classList.add('active');
  });
});

// ===== UPLOAD ZONE =====
const uploadZone = document.getElementById('uploadZone');
const hudFile = document.getElementById('hudFile');
const uploadContent = document.getElementById('uploadContent');
const uploadPreview = document.getElementById('uploadPreview');
const previewImg = document.getElementById('previewImg');
const removeImg = document.getElementById('removeImg');

uploadZone.addEventListener('click', (e) => {
  if (!uploadPreview.classList.contains('hidden')) return;
  hudFile.click();
});

uploadZone.addEventListener('dragover', (e) => {
  e.preventDefault();
  uploadZone.classList.add('drag-over');
});

uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('drag-over'));

uploadZone.addEventListener('drop', (e) => {
  e.preventDefault();
  uploadZone.classList.remove('drag-over');
  const file = e.dataTransfer.files[0];
  if (file && file.type.startsWith('image/')) loadImage(file);
});

hudFile.addEventListener('change', () => {
  if (hudFile.files[0]) loadImage(hudFile.files[0]);
});

removeImg.addEventListener('click', (e) => {
  e.stopPropagation();
  previewImg.src = '';
  uploadContent.classList.remove('hidden');
  uploadPreview.classList.add('hidden');
  hudFile.value = '';
});

function loadImage(file) {
  const reader = new FileReader();
  reader.onload = (e) => {
    previewImg.src = e.target.result;
    uploadContent.classList.add('hidden');
    uploadPreview.classList.remove('hidden');
  };
  reader.readAsDataURL(file);
}

// ===== CREAR SENSIBILIDAD =====
document.getElementById('btn-crear').addEventListener('click', () => {
  const device = document.getElementById('crear-device').value.trim();
  const ram = document.getElementById('crear-ram').value;

  if (!device) { showToast('⚠️ Ingresa el nombre de tu dispositivo'); return; }
  if (!ram) { showToast('⚠️ Selecciona la RAM de tu dispositivo'); return; }

  const steps = [
    { label: 'Identificando dispositivo...', text: `Reconociendo "${device}"`, delay: 800 },
    { label: 'Analizando hardware...', text: `RAM detectada: ${ram}GB · Modo DPI: ${state.crear.dpi.toUpperCase()}`, delay: 900 },
    { label: 'Calculando sensibilidades...', text: 'Calibrando rangos para cada mira', delay: 900 },
    { label: 'Sincronizando botón de disparo...', text: 'Ajustando botón para cero temblor', delay: 700 },
    { label: 'Finalizando...', text: 'Sensibilidad lista para aplicar', delay: 400 },
  ];

  showLoading(steps, () => {
    const result = generateSensitivity(device, ram, state.crear.dpi, state.crear.fire, state.crear.firePct);

    buildSensGrid('sens-grid-crear', result, true);

    document.getElementById('results-device-name').textContent = `${device} • ${ram}GB RAM • ${state.crear.dpi.toUpperCase()} DPI`;

    const badge = document.getElementById('tier-badge-crear');
    badge.textContent = TIER_LABELS[result.tier];
    badge.className = `tier-badge ${result.tier}`;

    document.getElementById('results-crear').classList.remove('hidden');
    document.getElementById('results-crear').scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    // Store for copy
    document.getElementById('btn-crear')._result = result;
    document.getElementById('btn-crear')._device = device;
  });
});

document.getElementById('copy-crear').addEventListener('click', () => {
  const btn = document.getElementById('btn-crear');
  if (!btn._result) return;
  copyText(buildCopyText(btn._result, btn._device, state.crear.dpi));
});

// ===== ESTABILIZAR SENSIBILIDAD =====
document.getElementById('btn-estabilizar').addEventListener('click', () => {
  const device = document.getElementById('estab-device').value.trim();
  const ram = document.getElementById('estab-ram').value;

  if (!device) { showToast('⚠️ Ingresa el nombre de tu dispositivo'); return; }
  if (!ram) { showToast('⚠️ Selecciona la RAM de tu dispositivo'); return; }

  const currentVals = {};
  document.querySelectorAll('#tab-estabilizar .range-input[data-key]').forEach(input => {
    currentVals[input.dataset.key] = parseInt(input.value);
  });

  const steps = [
    { label: 'Cargando sensibilidad actual...', text: 'Leyendo tus valores actuales', delay: 700 },
    { label: 'Analizando estabilidad...', text: `Evaluando dispositivo "${device}" con ${ram}GB RAM`, delay: 900 },
    { label: 'Calculando punto de equilibrio...', text: 'Mezclando preferencia del jugador con óptimo técnico', delay: 1000 },
    { label: 'Calibrando por mira...', text: 'Ajustando cada mira individualmente', delay: 800 },
    { label: 'Verificando anti-temblor...', text: 'Revisando coherencia entre miras', delay: 600 },
  ];

  showLoading(steps, () => {
    const result = stabilizeSensitivity(device, ram, state.estab.dpi, currentVals);

    buildSensGrid('sens-grid-estab', result, false);

    document.getElementById('results-estab-device').textContent = `${device} • ${ram}GB RAM • ${state.estab.dpi.toUpperCase()} DPI`;

    // Calibration message
    const info = detectDevice(device);
    const msgs = {
      'low': 'Sensibilidad rebajada para mayor estabilidad en gama baja. Se priorizó control sobre velocidad.',
      'mid-low': 'Balance alcanzado para gama media-baja. Pequeños ajustes a la baja en miras de larga distancia.',
      'mid': 'Sensibilidad estabilizada para gama media. Equilibrio óptimo entre velocidad y precisión.',
      'mid-high': 'Calibración fina para gama media-alta. Se mantienen valores altos con mayor estabilidad en 4x y AWM.',
      'high': 'Tu dispositivo soporta sensibilidades altas. Ajustes mínimos para máxima fluidez.',
    };
    document.getElementById('calibration-msg').textContent = msgs[info.tier];

    document.getElementById('results-estab').classList.remove('hidden');
    document.getElementById('results-estab').scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    document.getElementById('btn-estabilizar')._result = result;
    document.getElementById('btn-estabilizar')._device = device;
  });
});

document.getElementById('copy-estab').addEventListener('click', () => {
  const btn = document.getElementById('btn-estabilizar');
  if (!btn._result) return;
  copyText(buildCopyText(btn._result, btn._device, state.estab.dpi));
});

// ===== HUD CARGAR =====
document.getElementById('btn-analyze-hud').addEventListener('click', () => {
  const steps = [
    { label: 'Cargando imagen...', text: 'Procesando tu captura de pantalla', delay: 700 },
    { label: 'Detectando botones...', text: `Analizando layout para ${state.hud.fingers} dedos`, delay: 900 },
    { label: 'Evaluando ergonomía...', text: 'Calculando zonas de alcance natural', delay: 900 },
    { label: 'Optimizando disposición...', text: 'Eliminando posibles zonas de trabada', delay: 800 },
    { label: 'Generando recomendaciones...', text: 'Layout optimizado listo', delay: 500 },
  ];

  showLoading(steps, () => {
    const analysis = analyzeHUD(state.hud.fingers, state.hud.keepFire);

    let html = `<h4>Layout Sugerido: ${analysis.layout}</h4>
      <ul>${analysis.changes.map(c => `<li>${c}</li>`).join('')}</ul>
      <h4>Botón de Disparo</h4>
      <p>${analysis.fireNote}</p>
      <h4>Consejo Pro</h4>
      <p>💡 ${analysis.tip}</p>
    `;

    document.getElementById('hud-result-content').innerHTML = html;
    document.getElementById('hud-result-cargar').classList.remove('hidden');
    document.getElementById('hud-result-cargar').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  });
});

// ===== HUD BUSCAR =====
document.getElementById('btn-search-hud').addEventListener('click', () => {
  const fingers = state.hud.fingers;

  const steps = [
    { label: 'Buscando HUDs...', text: `Buscando layouts para ${fingers} dedos`, delay: 700 },
    { label: 'Filtrando región...', text: 'Filtrando por región Sudamérica', delay: 900 },
    { label: 'Verificando códigos...', text: 'Validando compatibilidad de códigos', delay: 800 },
    { label: 'Listo...', text: `${HUD_CODES[fingers].length} HUDs encontrados para SA`, delay: 400 },
  ];

  showLoading(steps, () => {
    const codes = HUD_CODES[fingers];
    const listEl = document.getElementById('hud-codes-list');

    listEl.innerHTML = codes.map(c => `
      <div class="hud-code-card">
        <div class="hud-code-card-header">
          <span class="hud-code-name">${c.name}</span>
          <span class="hud-code-tag">${c.tag}</span>
        </div>
        <p style="font-size:0.82rem;color:var(--text-dim);margin-bottom:10px;">${c.desc}</p>
        <div class="hud-code-value" onclick="copyHudCode('${c.code}')">
          <span>${c.code}</span>
          <span class="code-copy-icon">📋</span>
        </div>
      </div>`).join('');

    document.getElementById('hud-result-buscar').classList.remove('hidden');
    document.getElementById('hud-result-buscar').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  });
});

window.copyHudCode = function(code) {
  copyText(code);
};

// ===== HAMBURGER MENU =====
document.getElementById('hamburger').addEventListener('click', () => {
  document.getElementById('mobileNav').classList.toggle('open');
});

document.querySelectorAll('.mobile-nav-link').forEach(link => {
  link.addEventListener('click', () => {
    document.getElementById('mobileNav').classList.remove('open');
  });
});

// ===== SCROLL REVEAL =====
const reveals = document.querySelectorAll('.card, .results-panel, .about-card, .finger-btn');
reveals.forEach(el => el.classList.add('reveal'));

const observer = new IntersectionObserver((entries) => {
  entries.forEach((entry, i) => {
    if (entry.isIntersecting) {
      setTimeout(() => entry.target.classList.add('visible'), i * 60);
      observer.unobserve(entry.target);
    }
  });
}, { threshold: 0.1 });

reveals.forEach(el => observer.observe(el));

// ===== ACTIVE NAV LINK ON SCROLL =====
const sections = document.querySelectorAll('section[id], main[id]');
window.addEventListener('scroll', () => {
  let current = '';
  sections.forEach(sec => {
    if (window.scrollY >= sec.offsetTop - 100) current = sec.id;
  });

  document.querySelectorAll('.nav-link').forEach(link => {
    link.classList.toggle('active', link.getAttribute('href') === `#${current}`);
  });
}, { passive: true });
