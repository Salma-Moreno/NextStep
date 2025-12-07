<?php
session_start();
require '../Conexiones/db.php';

// Solo Staff
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Staff') {
    header('Location: StaffLogin.php');
    exit;
}

// Traer compañías
$companies = mysqli_query($conn, "SELECT ID_Company, Name FROM company ORDER BY Name ASC");

// Traer sucursales
$branches = mysqli_query($conn, "
    SELECT cp.*, c.Name AS companyName
    FROM collection_point cp
    LEFT JOIN company c ON cp.FK_ID_Company = c.ID_Company
    ORDER BY cp.Name ASC
");

// Verificar errores en consultas
if (!$branches) {
    die("Error SQL: " . mysqli_error($conn));
}

// Agrupar sucursales por compañía
$grouped = [];
mysqli_data_seek($branches, 0);
while ($b = mysqli_fetch_assoc($branches)) {
    $company = $b['companyName'] ?? "Sin compañía";
    $grouped[$company][] = $b;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Sucursales - NextStep</title>
<link rel="stylesheet" href="../assets/Staff/Branches.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<style>
/* ESTILOS PARA ERRORES DE VALIDACIÓN */
.toast {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 12px 20px;
    border-radius: 8px;
    color: white;
    font-weight: 500;
    z-index: 10000;
    opacity: 0;
    transform: translateY(-20px);
    transition: all 0.3s ease;
    max-width: 350px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.toast.show {
    opacity: 1;
    transform: translateY(0);
}
.toast.info { background: #007bff; }
.toast.success { background: #10b981; }
.toast.warning { background: #f59e0b; }
.toast.error { background: #dc2626; }

.error-message {
    color: #dc2626;
    font-size: 0.85rem;
    margin-top: 0.25rem;
    display: none;
}

.input-error {
    border-color: #dc2626 !important;
    background-color: #fef2f2;
}

.input-success {
    border-color: #10b981 !important;
}

.autocomplete-box {
    position: absolute;
    background: white;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    width: 100%;
    max-height: 200px;
    overflow-y: auto;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    z-index: 1000;
}

.autocomplete-item {
    padding: 10px 12px;
    cursor: pointer;
    border-bottom: 1px solid #f3f4f6;
}

.autocomplete-item:hover {
    background-color: #f3f4f6;
}

.autocomplete-item:last-child {
    border-bottom: none;
}

/* ESTILOS PARA CAMPOS OBLIGATORIOS */
.required-field::after {
    content: " *";
    color: #dc2626;
}

/* ESTILOS PARA EL MAPA */
#map {
    border: 1px solid #d1d5db;
}

/* ESTILOS PARA FORMULARIO EN MODAL */
.modal input,
.modal select {
    width: 100%;
    padding: 8px 12px;
    margin: 6px 0 12px 0;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.95rem;
}

.modal label {
    font-weight: 500;
    color: #374151;
    font-size: 0.9rem;
}

.address-row {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-bottom: 12px;
}

.address-row input {
    flex: 1;
    margin: 0;
}

/* ESTILOS PARA PAGINACIÓN */
.pagination-controls {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 20px;
}

.btn.small {
    padding: 6px 12px;
    font-size: 0.85rem;
}

.btn.danger {
    background: #dc2626;
}

.btn.danger:hover {
    background: #b91c1c;
}

/* ESTILOS PARA TARJETAS DE SUCURSAL */
.branch-card {
    background: white;
    border-radius: 12px;
    padding: 16px;
    margin: 10px 0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
}

.branch-card h3 {
    margin: 0 0 10px 0;
    color: #1f2937;
    font-size: 1.1rem;
}

.branch-card p {
    margin: 5px 0;
    font-size: 0.9rem;
    color: #6b7280;
}

.branch-card .actions {
    display: flex;
    gap: 8px;
    margin-top: 12px;
}
</style>
</head>
<body>

<?php include '../Includes/HeaderMenuStaff.php'; ?>

<div class="container">
    <h1>Sucursales</h1>
    <button class="btn primary" onclick="openAddModal()">+ Agregar sucursal</button>

    <div class="branch-list">
        <?php foreach ($grouped as $companyName => $branchList): 
            $companyId = preg_replace('/[^a-zA-Z0-9]/', '_', $companyName);
        ?>
            <h2><?= htmlspecialchars($companyName) ?></h2>
            <div class="company-wrapper" id="wrapper_<?= $companyId ?>" data-pages="<?= ceil(count($branchList)/5) ?>">
                <div class="company-page" id="page_<?= $companyId ?>"></div>
                <div class="pagination-controls">
                    <button class="btn small" onclick="prevPage('<?= $companyId ?>')">Anterior</button>
                    <button class="btn small" onclick="nextPage('<?= $companyId ?>')">Siguiente</button>
                </div>
                <script>
                    window.companyData = window.companyData || {};
                    window.companyData["<?= $companyId ?>"] = <?= json_encode($branchList, JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                </script>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ================= MODAL ================= -->
<div id="modal" class="modal-backdrop" onclick="closeModal(event)" style="display:none;">
    <div class="modal" onclick="event.stopPropagation();">
        <h2 id="formTitle">Nueva sucursal</h2>
        <form id="branchForm">
            <input type="hidden" name="id" id="id">
            <input type="hidden" name="latitude" id="latitude">
            <input type="hidden" name="longitude" id="longitude">

            <label class="required-field">Nombre</label>
            <input type="text" name="name" id="name" required>
            <div class="error-message" id="error-name"></div>

            <label class="required-field">Dirección</label>
            <div class="address-row">
                <input type="text" name="address" id="address" autocomplete="off" oninput="autocompleteAddress()" required placeholder="Ej: Calle Principal 123, Colonia Centro">
                <button type="button" id="btnSearch" class="btn small" onclick="searchAddress()">Buscar</button>
            </div>
            <div id="autocomplete-list" class="autocomplete-box" style="display:none;"></div>
            <div class="error-message" id="error-address"></div>

            <label>Teléfono (10 dígitos)</label>
            <input type="text" name="phone" id="phone" placeholder="6641234567" maxlength="10">
            <div class="error-message" id="error-phone"></div>

            <label class="required-field">Compañía</label>
            <select name="company" id="company" required>
                <option value="">Seleccione una compañía...</option>
                <?php mysqli_data_seek($companies, 0); while ($c = mysqli_fetch_assoc($companies)): ?>
                    <option value="<?= $c['ID_Company'] ?>"><?= htmlspecialchars($c['Name']) ?></option>
                <?php endwhile; ?>
            </select>
            <div class="error-message" id="error-company"></div>

            <label>Coordenadas</label>
            <div style="display: flex; gap: 10px; margin-bottom: 12px;">
                <input type="text" id="latDisplay" placeholder="Latitud" readonly style="flex:1;">
                <input type="text" id="lonDisplay" placeholder="Longitud" readonly style="flex:1;">
            </div>

            <label>Vista previa en el mapa</label>
            <div id="map" style="height:320px;border-radius:8px;"></div>

            <div class="modal-actions" style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px;">
                <button type="button" class="btn secondary" onclick="closeModal(true)">Cancelar</button>
                <button type="submit" class="btn primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
let map, marker;
let currentPage = {};

// ================= TOAST =================
function showToast(msg, type="info") {
    const box = document.createElement("div");
    box.className = "toast " + type;
    box.innerText = msg;
    document.body.appendChild(box);
    requestAnimationFrame(()=> box.classList.add("show"));
    setTimeout(()=> { box.classList.remove("show"); setTimeout(()=> box.remove(), 300); }, 3000);
}

// ================= VALIDACIONES =================
function validateField(field) {
    const value = field.value.trim();
    const fieldId = field.id;
    let isValid = true;
    let errorMsg = '';
    
    const errorElement = document.getElementById(`error-${fieldId}`);
    
    switch(fieldId) {
        case 'name':
            if (!value) {
                errorMsg = 'El nombre es obligatorio';
                isValid = false;
            } else if (value.length < 3) {
                errorMsg = 'Mínimo 3 caracteres';
                isValid = false;
            } else if (/^[0-9\s\W]+$/.test(value)) {
                errorMsg = 'Debe contener letras';
                isValid = false;
            } else if (!/^[a-zA-ZÁÉÍÓÚáéíóúÑñ0-9\s&.,\-()]+$/.test(value)) {
                errorMsg = 'Caracteres no permitidos. Use solo letras, números, espacios y símbolos básicos';
                isValid = false;
            }
            break;
            
        case 'phone':
            if (value) {
                const cleanPhone = value.replace(/[^\d]/g, '');
                if (!/^\d+$/.test(cleanPhone)) {
                    errorMsg = 'Solo números (0-9)';
                    isValid = false;
                } else if (cleanPhone.length !== 10) {
                    errorMsg = 'Debe tener 10 dígitos';
                    isValid = false;
                }
            }
            break;
            
        case 'address':
            if (!value) {
                errorMsg = 'La dirección es obligatoria';
                isValid = false;
            } else if (value.length < 10) {
                errorMsg = 'Dirección muy corta (mínimo 10 caracteres)';
                isValid = false;
            }
            break;
            
        case 'company':
            if (!value) {
                errorMsg = 'Debe seleccionar una compañía';
                isValid = false;
            }
            break;
    }
    
    // Aplicar estilos
    if (!isValid) {
        field.classList.add('input-error');
        field.classList.remove('input-success');
        if (errorElement) {
            errorElement.textContent = errorMsg;
            errorElement.style.display = 'block';
        }
    } else if (value) {
        field.classList.remove('input-error');
        field.classList.add('input-success');
        if (errorElement) errorElement.style.display = 'none';
    } else {
        field.classList.remove('input-error', 'input-success');
        if (errorElement) errorElement.style.display = 'none';
    }
    
    return isValid;
}

function clearError(field) {
    field.classList.remove('input-error');
    const errorElement = document.getElementById(`error-${field.id}`);
    if (errorElement) errorElement.style.display = 'none';
}

function validateForm() {
    const name = document.getElementById('name').value.trim();
    const address = document.getElementById('address').value.trim();
    const phone = document.getElementById('phone').value.trim();
    const company = document.getElementById('company').value;
    const lat = document.getElementById('latitude').value;
    const lon = document.getElementById('longitude').value;
    
    let errors = [];
    
    // Validar nombre
    if (!name) {
        errors.push("El nombre es obligatorio");
    } else if (name.length < 3) {
        errors.push("El nombre debe tener al menos 3 caracteres");
    } else if (/^[0-9\s\W]+$/.test(name)) {
        errors.push("El nombre debe contener letras");
    }
    
    // Validar dirección
    if (!address) {
        errors.push("La dirección es obligatoria");
    } else if (address.length < 10) {
        errors.push("Dirección muy corta (mínimo 10 caracteres)");
    }
    
    // Validar teléfono
    if (phone) {
        const cleanPhone = phone.replace(/[^\d]/g, '');
        if (!/^\d+$/.test(cleanPhone)) {
            errors.push("El teléfono debe contener solo números");
        } else if (cleanPhone.length !== 10) {
            errors.push("El teléfono debe tener 10 dígitos");
        }
    }
    
    // Validar compañía
    if (!company) {
        errors.push("Debe seleccionar una compañía");
    }
    
    // Validar coordenadas
    if (!lat || !lon) {
        errors.push("Debe buscar una dirección válida en el mapa");
    }
    
    if (errors.length > 0) {
        showToast(errors.join('\n'), 'error');
        return false;
    }
    
    return true;
}

// ================= MAPA =================
function initMap() {
    if (map) return;
    map = L.map("map", { preferCanvas: true }).setView([32.5149, -117.0382], 13);
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "&copy; OpenStreetMap contributors"
    }).addTo(map);
}

function refreshMap() { 
    setTimeout(()=> { 
        try{ map.invalidateSize(); } catch(e){} 
    }, 250); 
}

function updateMapMarker(lat, lon) {
    if (!map) initMap();
    if (marker) marker.remove();
    marker = L.marker([lat, lon]).addTo(map);
    map.flyTo([lat, lon], 16, {animate:true, duration:0.9});
    
    // Actualizar display de coordenadas
    document.getElementById('latitude').value = lat;
    document.getElementById('longitude').value = lon;
    document.getElementById('latDisplay').value = lat.toFixed(6);
    document.getElementById('lonDisplay').value = lon.toFixed(6);
}

async function searchAddress() {
    const address = document.getElementById("address").value.trim();
    const errorElement = document.getElementById('error-address');
    
    if(!address) {
        if (errorElement) {
            errorElement.textContent = "Ingrese una dirección";
            errorElement.style.display = 'block';
        }
        showToast("Ingresa una dirección","error");
        return;
    }
    
    if (address.length < 10) {
        if (errorElement) {
            errorElement.textContent = "Dirección muy corta (mínimo 10 caracteres)";
            errorElement.style.display = 'block';
        }
        showToast("Dirección muy corta","error");
        return;
    }
    
    const btn = document.getElementById("btnSearch"); 
    btn.disabled = true; 
    btn.innerText = "Buscando...";
    
    try {
        const formData = new FormData(); 
        formData.append("address", address);
        
        showToast("Buscando dirección...", "info");
        
        const res = await fetch("Branches_Search.php", { method: "POST", body: formData });
        const data = await res.json();
        
        if (!data.success) { 
            if (errorElement) {
                errorElement.textContent = "Dirección no encontrada. Intente con otra";
                errorElement.style.display = 'block';
            }
            showToast("No encontrado, usando ubicación por defecto","warning");
            document.getElementById("latitude").value = 32.5149;
            document.getElementById("longitude").value = -117.0382;
            updateMapMarker(32.5149, -117.0382);
        } else {
            const lat = parseFloat(data.latitude);
            const lon = parseFloat(data.longitude);
            
            if (errorElement) errorElement.style.display = 'none';
            document.getElementById("latitude").value = lat;
            document.getElementById("longitude").value = lon;
            updateMapMarker(lat, lon);
            showToast("Dirección localizada","success");
        }
    } catch(e) { 
        console.warn(e); 
        showToast("Error al buscar dirección","error");
    } finally {
        btn.disabled = false; 
        btn.innerText = "Buscar";
    }
}

// ================= MODAL =================
function openAddModal() {
    document.getElementById("formTitle").innerText = "Nueva sucursal";
    document.getElementById("branchForm").dataset.action = "Branches_Save.php";
    
    // Limpiar campos
    ["id", "name", "address", "phone", "company", "latitude", "longitude", "latDisplay", "lonDisplay"]
        .forEach(field => {
            const element = document.getElementById(field);
            if (element) element.value = "";
        });
    
    // Limpiar errores
    document.querySelectorAll('.error-message').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.input-error, .input-success').forEach(el => {
        el.classList.remove('input-error', 'input-success');
    });
    
    // Limpiar mapa
    if (marker) marker.remove();
    
    document.getElementById("modal").style.display = "flex";
    initMap(); 
    refreshMap();
    
    // Establecer ubicación por defecto
    updateMapMarker(32.5149, -117.0382);
}

function openEditModal(data) {
    document.getElementById("formTitle").innerText = "Editar sucursal";
    document.getElementById("branchForm").dataset.action = "Branches_Update.php";
    
    // Rellenar campos
    document.getElementById("id").value = data.ID_Point || "";
    document.getElementById("name").value = data.Name || "";
    document.getElementById("address").value = data.address || "";
    document.getElementById("phone").value = data.Phone_number || "";
    document.getElementById("company").value = data.FK_ID_Company || "";
    
    // Limpiar errores
    document.querySelectorAll('.error-message').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.input-error, .input-success').forEach(el => {
        el.classList.remove('input-error', 'input-success');
    });
    
    initMap();
    
    const lat = parseFloat(data.latitude) || 32.5149;
    const lon = parseFloat(data.longitude) || -117.0382;
    
    if (marker) marker.remove();
    marker = L.marker([lat, lon]).addTo(map);
    map.setView([lat, lon], 15);
    
    document.getElementById("latitude").value = lat;
    document.getElementById("longitude").value = lon;
    document.getElementById("latDisplay").value = lat.toFixed(6);
    document.getElementById("lonDisplay").value = lon.toFixed(6);
    
    document.getElementById("modal").style.display = "flex";
    refreshMap();
}

function closeModal(event) { 
    if (event === true || (event.target && event.target.id === "modal")) {
        document.getElementById("modal").style.display = "none";
    }
}

// ================= CRUD =================
document.getElementById("branchForm").addEventListener("submit", async function(e) {
    e.preventDefault();
    
    // Validar antes de enviar
    if (!validateForm()) {
        return false;
    }
    
    const lat = document.getElementById("latitude").value;
    const lon = document.getElementById("longitude").value;
    
    if (!lat || !lon) {
        showToast("Coordenadas obligatorias", "error");
        return;
    }
    
    const formData = new FormData(this);
    const action = this.dataset.action;
    
    try {
        showToast("Guardando sucursal...", "info");
        
        const res = await fetch(action, { method: "POST", body: formData });
        const data = await res.json();
        
        if (data.success) {
            showToast("Sucursal guardada exitosamente", "success");
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.error || data.msg || "Error al guardar", "error");
        }
    } catch(e) { 
        console.error(e); 
        showToast("Error de conexión", "error");
    }
});

async function deleteBranch(id) {
    if (!confirm("¿Está seguro de eliminar esta sucursal?\nEsta acción no se puede deshacer.")) {
        return;
    }
    
    try {
        showToast("Eliminando sucursal...", "info");
        
        const res = await fetch("Branches_Delete.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "id=" + encodeURIComponent(id)
        });
        
        const data = await res.json();
        
        if (data.success) { 
            showToast("Sucursal eliminada", "success"); 
            setTimeout(() => location.reload(), 1000); 
        } else {
            showToast(data.error || "Error al eliminar", "error");
        }
    } catch(e) { 
        showToast("Error de red", "error"); 
    }
}

// ================= PAGINACIÓN =================
function renderCompanyPage(companyId) {
    const wrapper = document.getElementById("wrapper_" + companyId);
    const totalPages = parseInt(wrapper.dataset.pages);
    const data = window.companyData[companyId];
    const pageContainer = document.getElementById("page_" + companyId);
    const page = currentPage[companyId] || 1;
    const perPage = 4;
    const start = (page - 1) * perPage;
    const items = data.slice(start, start + perPage);
    
    pageContainer.innerHTML = items.map(b => `
        <div class="branch-card">
            <h3>${b.Name}</h3>
            <p><strong>Dirección:</strong> ${b.address}</p>
            <p><strong>Teléfono:</strong> ${b.Phone_number || 'No registrado'}</p>
            <p><strong>Compañía:</strong> ${b.companyName}</p>
            <p><strong>Coordenadas:</strong> ${parseFloat(b.latitude).toFixed(6)}, ${parseFloat(b.longitude).toFixed(6)}</p>
            <div class="actions">
                <button class="btn small" onclick='openEditModal(${JSON.stringify(b)})'>Editar</button>
                <button class="btn small danger" onclick="deleteBranch(${b.ID_Point})">Eliminar</button>
            </div>
        </div>
    `).join("");
    
    const buttons = wrapper.querySelectorAll(".pagination-controls button");
    if (buttons[0]) buttons[0].disabled = page <= 1;
    if (buttons[1]) buttons[1].disabled = page >= totalPages;
}

function nextPage(companyId) { 
    currentPage[companyId] = (currentPage[companyId] || 1) + 1; 
    renderCompanyPage(companyId); 
}

function prevPage(companyId) { 
    currentPage[companyId] = (currentPage[companyId] || 1) - 1; 
    renderCompanyPage(companyId); 
}

// ================= AUTOCOMPLETE =================
let autocompleteTimeout = null;

async function autocompleteAddress() {
    const query = document.getElementById("address").value.trim();
    const box = document.getElementById("autocomplete-list");
    
    if (!query || query.length < 3) { 
        box.style.display = "none"; 
        box.innerHTML = ""; 
        return; 
    }
    
    clearTimeout(autocompleteTimeout);
    
    autocompleteTimeout = setTimeout(async () => {
        try {
            const company = document.getElementById("company").value || "";
            const res = await fetch("Branches_Autocomplete.php?q=" + encodeURIComponent(query) + "&company=" + encodeURIComponent(company));
            
            if (!res.ok) { 
                box.style.display = "none"; 
                return; 
            }
            
            const data = await res.json();
            box.innerHTML = "";
            
            if (!Array.isArray(data) || data.length === 0) { 
                box.style.display = "none"; 
                return; 
            }
            
            data.forEach(item => {
                const div = document.createElement("div");
                div.className = "autocomplete-item";
                div.innerText = item.display_name;
                div.onclick = () => { 
                    document.getElementById("address").value = item.display_name;
                    document.getElementById("latitude").value = item.lat;
                    document.getElementById("longitude").value = item.lon;
                    updateMapMarker(parseFloat(item.lat), parseFloat(item.lon));
                    box.style.display = "none";
                    showToast("Dirección seleccionada", "success");
                };
                box.appendChild(div);
            });
            
            box.style.display = "block";
        } catch(e) { 
            console.warn(e); 
            box.style.display = "none"; 
        }
    }, 500);
}

// Validación en tiempo real
document.querySelectorAll('#branchForm input, #branchForm select').forEach(input => {
    input.addEventListener('blur', function() {
        validateField(this);
    });
    
    input.addEventListener('input', function() {
        clearError(this);
    });
});

// Cerrar autocomplete al hacer clic fuera
document.addEventListener("click", function(event) {
    const box = document.getElementById("autocomplete-list");
    if (!event.target.closest("#autocomplete-list") && !event.target.closest("#address")) {
        if (box) box.style.display = "none";
    }
});

// ================= INICIALIZACIÓN =================
window.onload = function() {
    initMap();
    
    for (const companyId in window.companyData) {
        currentPage[companyId] = 1;
        renderCompanyPage(companyId);
    }
    
    // Cerrar modal con ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('modal').style.display === 'flex') {
            closeModal(true);
        }
    });
};
</script>
</body>
</html>