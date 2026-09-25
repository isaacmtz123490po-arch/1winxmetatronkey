const STORE_KEY = 'metatron_content';
const CODE = "001";

// Valores por defecto
const DEFAULTS = {
  heroTitle: "Damos estructura a lo que tu empresa aún no ha ordenado.",
  heroText: "Metatron diseña sistemas, procesos y arquitecturas digitales para negocios que necesitan operar con precisión, no con improvisación.",
  heroImg: "",
  email: "hola@metatron.com",
  phone: "+52 55 0000 0000",
  address: "Ciudad de México, México"
};

let pendingImageData = null;

// ------------------- CARGAR CONTENIDO AL INICIAR -------------------
function loadContent() {
  try {
    const saved = localStorage.getItem(STORE_KEY);
    const d = saved ? JSON.parse(saved) : DEFAULTS;
    applyContent(d);
  } catch (e) {
    console.error('Error al cargar contenido:', e);
    applyContent(DEFAULTS);
  }
}

function applyContent(d) {
  if (d.heroTitle) document.getElementById('hero-title').textContent = d.heroTitle;
  if (d.heroText) document.getElementById('hero-text').textContent = d.heroText;
  if (d.heroImg) {
    const img = document.getElementById('hero-img');
    img.src = d.heroImg;
    img.style.display = 'block';
  }
  if (d.email) {
    document.getElementById('contact-email').textContent = d.email;
    document.getElementById('contact-email-label').textContent = d.email;
    document.getElementById('contact-mailto').href = 'mailto:' + d.email;
  }
  if (d.phone) document.getElementById('contact-phone').textContent = d.phone;
  if (d.address) document.getElementById('contact-address').textContent = d.address;
}

// ------------------- GUARDAR CONTENIDO -------------------
function saveContent() {
  const d = {
    heroTitle: document.getElementById('f-title').value,
    heroText: document.getElementById('f-text').value,
    heroImg: pendingImageData || null,
    email: document.getElementById('f-email').value,
    phone: document.getElementById('f-phone').value,
    address: document.getElementById('f-address').value
  };

  try {
    localStorage.setItem(STORE_KEY, JSON.stringify(d));
    applyContent(d);
    showSaveMessage('✓ Cambios guardados correctamente.');
    setTimeout(closeAdmin, 1200);
  } catch (e) {
    showSaveMessage('✗ No se pudo guardar.', true);
  }
}

// ------------------- RESTABLECER -------------------
function resetToDefaults() {
  if (confirm('¿Restablecer todo a los valores originales? Esta acción no se puede deshacer.')) {
    localStorage.removeItem(STORE_KEY);
    pendingImageData = null;
    applyContent(DEFAULTS);
    showPanel();
    showSaveMessage('✓ Restablecido a valores originales.');
  }
}

// ------------------- MODAL -------------------
const modal = document.getElementById('admin-modal');
const modalTitle = document.getElementById('modal-title');
const modalBody = document.getElementById('modal-body-content');
const modalFooter = document.getElementById('modal-footer');

function openAdmin() {
  modal.style.display = 'flex';
  void modal.offsetWidth;
  modal.classList.add('open');
  showPinScreen();
}

function closeAdmin() {
  modal.classList.remove('open');
  setTimeout(() => { modal.style.display = 'none'; }, 300);
  pendingImageData = null;
}

function showPinScreen() {
  modalTitle.textContent = 'Acceso restringido';
  modalFooter.style.display = 'none';
  modalBody.innerHTML = `
    <div class="pin-wrapper">
      <p>Introduce la clave para acceder al panel de administración</p>
      <div class="pin-row">
        <input type="tel" inputmode="numeric" maxlength="1" placeholder="0" id="p0" autocomplete="off">
        <input type="tel" inputmode="numeric" maxlength="1" placeholder="0" id="p1" autocomplete="off">
        <input type="tel" inputmode="numeric" maxlength="1" placeholder="0" id="p2" autocomplete="off">
      </div>
      <div class="card-error" id="pin-error"></div>
    </div>`;
  bindPinInputs();
}

function bindPinInputs() {
  const fields = ['p0', 'p1', 'p2'].map(id => document.getElementById(id));
  fields.forEach((el, i) => {
    el.addEventListener('input', () => {
      el.value = el.value.replace(/[^0-9]/g, '');
      if (el.value && fields[i + 1]) fields[i + 1].focus();
      if (fields.every(f => f.value.length === 1)) {
        const entered = fields.map(f => f.value).join('');
        if (entered === CODE) {
          showPanel();
        } else {
          document.getElementById('pin-error').textContent = 'Clave incorrecta';
          fields.forEach(f => f.value = '');
          fields[0].focus();
        }
      }
    });
    el.addEventListener('keydown', (e) => {
      if (e.key === 'Backspace' && !el.value && i > 0) {
        fields[i - 1].focus();
      }
    });
  });
}

function getCurrentData() {
  try {
    const saved = localStorage.getItem(STORE_KEY);
    return saved ? JSON.parse(saved) : DEFAULTS;
  } catch {
    return DEFAULTS;
  }
}

function escapeHtml(str) {
  if (!str) return '';
  return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function showPanel() {
  modalTitle.textContent = 'Panel de Administración';
  modalFooter.style.display = 'flex';
  const d = getCurrentData();
  pendingImageData = d.heroImg || null;

  modalBody.innerHTML = `
    <div class="stats-row">
      <div class="stat-card">
        <div class="stat-value">3</div>
        <div class="stat-label">Mensajes nuevos</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">128</div>
        <div class="stat-label">Visitas hoy</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">✓</div>
        <div class="stat-label">En línea</div>
      </div>
    </div>

    <div class="panel-section">
      <div class="panel-section-title">Contenido principal</div>
      <div class="form-group">
        <label for="f-title">Título principal</label>
        <input id="f-title" value="${escapeHtml(d.heroTitle)}">
      </div>
      <div class="form-group">
        <label for="f-text">Texto descriptivo</label>
        <textarea id="f-text" rows="3">${escapeHtml(d.heroText)}</textarea>
      </div>
      <div class="form-group">
        <label>Imagen de portada</label>
        <div class="file-upload" id="file-zone">
          <label class="file-label" for="f-img">
            <span>📎</span> Haz clic para subir una imagen<br>
            <span>Formatos: JPG, PNG, SVG · Máx: 5MB</span>
          </label>
          <input id="f-img" type="file" accept="image/*">
        </div>
        <div class="image-preview" id="img-preview">
          <button class="btn-remove-image" id="remove-img" title="Eliminar imagen">✕</button>
          <img id="preview-img" src="" alt="Vista previa">
          <div class="image-preview-name" id="img-name"></div>
        </div>
      </div>
    </div>

    <div class="panel-section">
      <div class="panel-section-title">Información de contacto</div>
      <div class="form-group">
        <label for="f-email">Correo electrónico</label>
        <input id="f-email" value="${escapeHtml(d.email)}">
      </div>
      <div class="form-group">
        <label for="f-phone">Teléfono</label>
        <input id="f-phone" value="${escapeHtml(d.phone)}">
      </div>
      <div class="form-group">
        <label for="f-address">Ubicación</label>
        <input id="f-address" value="${escapeHtml(d.address)}">
      </div>
    </div>
  `;

  if (pendingImageData) {
    showImagePreview(pendingImageData, 'Imagen guardada');
  }
  bindImageUpload();
}

function bindImageUpload() {
  const fileInput = document.getElementById('f-img');
  const preview = document.getElementById('img-preview');
  const previewImg = document.getElementById('preview-img');
  const imgName = document.getElementById('img-name');
  const removeBtn = document.getElementById('remove-img');

  fileInput.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (!file) return;

    if (file.size > 5 * 1024 * 1024) {
      showSaveMessage('✗ El archivo pesa más de 5MB.', true);
      return;
    }

    const reader = new FileReader();
    reader.onload = (ev) => {
      pendingImageData = ev.target.result;
      showImagePreview(pendingImageData, file.name);
    };
    reader.readAsDataURL(file);
  });

  removeBtn.addEventListener('click', () => {
    pendingImageData = null;
    preview.classList.remove('show');
    fileInput.value = '';
  });
}

function showImagePreview(src, name) {
  const preview = document.getElementById('img-preview');
  const previewImg = document.getElementById('preview-img');
  const imgName = document.getElementById('img-name');
  previewImg.src = src;
  imgName.textContent = name;
  preview.classList.add('show');
}

function showSaveMessage(text, isError = false) {
  const status = document.getElementById('save-status');
  status.textContent = text;
  status.className = 'save-message' + (isError ? ' error' : '');
  clearTimeout(window._saveMsgTimer);
  window._saveMsgTimer = setTimeout(() => {
    status.classList.add('hidden');
  }, 3500);
}

// ------------------- INICIALIZACIÓN Y EVENTOS -------------------
document.addEventListener('DOMContentLoaded', () => {
  // Cargar contenido guardado al entrar a la página
  loadContent();

  // Animación de entrada
  setTimeout(() => {
    document.getElementById('intro').classList.add('fade-out');
    document.body.classList.add('revealed');
  }, 2800);

  // Botón para abrir admin
  document.getElementById('admin-trigger').addEventListener('click', openAdmin);

  // Botón cerrar modal
  document.querySelector('.modal-close').addEventListener('click', closeAdmin);

  // Cerrar al hacer clic fuera del modal
  modal.addEventListener('click', (e) => {
    if (e.target === modal) closeAdmin();
  });

  // Botones guardar y restablecer
  document.getElementById('btn-save').addEventListener('click', saveContent);
  document.getElementById('btn-reset').addEventListener('click', resetToDefaults);
});
