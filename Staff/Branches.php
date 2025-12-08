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

            <label>Nombre</label>
            <input type="text" name="name" id="name" required>

            <label>Dirección</label>
            <div class="address-row" style="display:flex;gap:8px;align-items:center;">
                <input type="text" name="address" id="address" autocomplete="off" oninput="autocompleteAddress()" required style="flex:1;">
                <button type="button" id="btnSearch" class="btn small" onclick="searchAddress()">Buscar</button>
            </div>
            <div id="autocomplete-list" class="autocomplete-box" style="display:none;position:relative;z-index:9999;"></div>

            <label>Teléfono</label>
            <input type="text" name="phone" id="phone">

            <label>Compañía</label>
            <select name="company" id="company" required>
                <option value="">Seleccione...</option>
                <?php mysqli_data_seek($companies, 0); while ($c = mysqli_fetch_assoc($companies)): ?>
                    <option value="<?= $c['ID_Company'] ?>"><?= htmlspecialchars($c['Name']) ?></option>
                <?php endwhile; ?>
            </select>

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
    setTimeout(()=> { 
        box.classList.remove("show"); 
        setTimeout(()=> box.remove(), 300); 
    }, 3000);
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
}
async function searchAddress() {
    const address = document.getElementById("address").value.trim();
    if(address===""){ 
        showToast("Ingresa una dirección","error"); 
        return; 
    }
    const btn=document.getElementById("btnSearch"); 
    btn.disabled=true; 
    btn.innerText="Buscando...";
    try{
        const formData=new FormData(); 
        formData.append("address",address);
        const res = await fetch("Branches_Search.php",{method:"POST",body:formData});
        const data = await res.json();
        if(!data.success){ 
            showToast("No encontrado, se usará Tijuana","warning");
            document.getElementById("latitude").value=32.5149;
            document.getElementById("longitude").value=-117.0382;
            updateMapMarker(32.5149,-117.0382);
            btn.disabled=false; 
            btn.innerText="Buscar"; 
            return;
        }
        const lat=parseFloat(data.latitude), lon=parseFloat(data.longitude);
        document.getElementById("latitude").value=lat;
        document.getElementById("longitude").value=lon;
        updateMapMarker(lat,lon);
        showToast("Dirección localizada","success");
    } catch(e){ 
        console.warn(e); 
        showToast("Error al geocodificar","error"); 
    }
    btn.disabled=false; 
    btn.innerText="Buscar";
}

// ================= MODAL =================
function openAddModal(){
    document.getElementById("formTitle").innerText="Nueva sucursal";
    document.getElementById("branchForm").dataset.action="Branches_Save.php";
    ["id","name","address","phone","company","latitude","longitude"].forEach(f=>document.getElementById(f).value="");
    if(marker) marker.remove();
    document.getElementById("modal").style.display="flex";
    initMap(); 
    refreshMap();
}
function openEditModal(data){
    document.getElementById("formTitle").innerText="Editar sucursal";
    document.getElementById("branchForm").dataset.action="Branches_Update.php";
    ["id","name","address","phone","company","latitude","longitude"].forEach(f=>{
        const key = (f === 'id') ? 'ID_Point' : f;
        document.getElementById(f).value = data[key] || "";
    });
    initMap();
    const lat=parseFloat(data.latitude)||32.5149, 
          lon=parseFloat(data.longitude)||-117.0382;
    if(marker) marker.remove();
    marker=L.marker([lat,lon]).addTo(map);
    map.setView([lat,lon],15);
    document.getElementById("modal").style.display="flex";
    refreshMap();
}
function closeModal(event){ 
    if(event===true || (event.target && event.target.id==="modal")) 
        document.getElementById("modal").style.display="none"; 
}

// ================= CRUD =================
document.getElementById("branchForm").addEventListener("submit", async function(e){
    e.preventDefault();
    const lat=document.getElementById("latitude").value;
    const lon=document.getElementById("longitude").value;
    if(!lat || !lon){ 
        showToast("Coordenadas obligatorias","error"); 
        return; 
    }

    const formData=new FormData(this);
    const action=this.dataset.action;
    try{
        const res=await fetch(action,{method:"POST",body:formData});
        const data=await res.json();
        if(data.success){
            showToast("Sucursal guardada","success");
            setTimeout(()=>location.reload(),700);
        } else {
            // 🔹 AQUÍ USAMOS EL MENSAJE QUE MANDA PHP
            showToast(data.error || data.msg || "Error al guardar","error");
        }
    } catch(e){ 
        console.error(e); 
        showToast("Error de red","error"); 
    }
});

async function deleteBranch(id){
    if(!confirm("¿Eliminar esta sucursal?")) return;
    try{
        const res=await fetch("Branches_Delete.php",{
            method:"POST",
            headers:{"Content-Type":"application/x-www-form-urlencoded"},
            body:"id="+encodeURIComponent(id)
        });
        const data=await res.json();
        if(data.success){ 
            showToast("Sucursal eliminada","success"); 
            setTimeout(()=>location.reload(),700); 
        }
        else showToast("Error al eliminar","error");
    } catch(e){ 
        showToast("Error de red","error"); 
    }
}

// ================= PAGINACIÓN =================
function renderCompanyPage(companyId){
    const wrapper=document.getElementById("wrapper_"+companyId);
    const totalPages=parseInt(wrapper.dataset.pages);
    const data=window.companyData[companyId];
    const pageContainer=document.getElementById("page_"+companyId);
    const page=currentPage[companyId]||1;
    const perPage=4;
    const start=(page-1)*perPage;
    const items=data.slice(start,start+perPage);
    pageContainer.innerHTML=items.map(b=>`
        <div class="branch-card">
            <h3>${b.Name}</h3>
            <p><strong>Dirección:</strong> ${b.address}</p>
            <p><strong>Teléfono:</strong> ${b.Phone_number}</p>
            <p><strong>Compañía:</strong> ${b.companyName}</p>
            <div class="actions">
                <button class="btn small" onclick='openEditModal(${JSON.stringify(b)})'>Editar</button>
                <button class="btn small danger" onclick="deleteBranch(${b.ID_Point})">Eliminar</button>
            </div>
        </div>
    `).join("");
    const buttons=wrapper.querySelectorAll(".pagination-controls button");
    buttons[0].disabled=page<=1;
    buttons[1].disabled=page>=totalPages;
}
function nextPage(companyId){ 
    currentPage[companyId]=(currentPage[companyId]||1)+1; 
    renderCompanyPage(companyId); 
}
function prevPage(companyId){ 
    currentPage[companyId]=(currentPage[companyId]||1)-1; 
    renderCompanyPage(companyId); 
}

// ================= AUTOCOMPLETE =================
let autocompleteTimeout=null;
async function autocompleteAddress(){
    const query=document.getElementById("address").value.trim();
    const box=document.getElementById("autocomplete-list");
    if(!query || query.length<3){ 
        box.style.display="none"; 
        box.innerHTML=""; 
        return; 
    }
    clearTimeout(autocompleteTimeout);
    autocompleteTimeout=setTimeout(async ()=>{
        try{
            const company=document.getElementById("company").value||"";
            const res=await fetch("Branches_Autocomplete.php?q="+encodeURIComponent(query)+"&company="+encodeURIComponent(company));
            if(!res.ok){ 
                box.style.display="none"; 
                return; 
            }
            const data=await res.json();
            box.innerHTML="";
            if(!Array.isArray(data)||data.length===0){ 
                box.style.display="none"; 
                return; 
            }
            data.forEach(item=>{
                const div=document.createElement("div");
                div.className="autocomplete-item";
                div.innerText=item.display_name;
                div.onclick=()=>{ 
                    document.getElementById("address").value=item.display_name;
                    document.getElementById("latitude").value=item.lat;
                    document.getElementById("longitude").value=item.lon;
                    updateMapMarker(parseFloat(item.lat),parseFloat(item.lon));
                    box.style.display="none";
                };
                box.appendChild(div);
            });
            box.style.display="block";
        } catch(e){ 
            console.warn(e); 
            box.style.display="none"; 
        }
    },300);
}
document.addEventListener("click", function(event){
    const box=document.getElementById("autocomplete-list");
    if(!event.target.closest("#autocomplete-list")&&!event.target.closest("#address")) 
        box.style.display="none";
});

// ================= INICIALIZACIÓN =================
window.onload=function(){
    initMap();
    for(const companyId in window.companyData){
        currentPage[companyId]=1;
        renderCompanyPage(companyId);
    }
};
</script>
</body>
</html>
