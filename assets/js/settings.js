document.addEventListener("DOMContentLoaded", () => {

    initializeTabs();

    loadSettings();

    initializeButtons();
    loadAuditLogs();

});


function initializeTabs() {

    document.querySelectorAll(".tab-btn").forEach(button => {

        button.addEventListener("click", () => {

            document.querySelectorAll(".tab-btn")
                .forEach(btn => btn.classList.remove("active"));

            document.querySelectorAll(".tab-content")
                .forEach(tab => tab.classList.remove("active"));

            button.classList.add("active");

            document
                .getElementById(button.dataset.tab)
                .classList.add("active");

               if(button.dataset.tab === "backup"){

    loadBackups();

} 

        });

    });

}


function initializeButtons() {

    document.querySelectorAll(".save-btn").forEach(button => {

        button.addEventListener("click", saveSettings);

    });

}


async function loadSettings() {

    try {

        const response = await fetch("api/settings/get.php");

        const result = await response.json();

        if (!result.success) {

            showMessage("Unable to load settings.", "error");

            return;

        }

        populateFields(result.settings);
        if (result.settings.theme) {
    applyTheme(result.settings.theme);
}

    }

    catch (error) {

        console.error(error);

        showMessage("Failed to connect to server.", "error");

    }

}


function populateFields(settings) {

    Object.entries(settings).forEach(([key, value]) => {

        const field = document.getElementById(key);

        if (!field) return;

        switch (field.type) {

            case "checkbox":

                field.checked = value == "1";

                break;

            default:

                field.value = value;

        }

    });

}


async function saveSettings() {
    const data = {

    organization_name: document.getElementById("organization_name").value,
    organization_address: document.getElementById("organization_address").value,
    organization_phone: document.getElementById("organization_phone").value,

    smtp_host: document.getElementById("smtp_host").value,
    smtp_port: document.getElementById("smtp_port").value,
    smtp_from_email: document.getElementById("smtp_from_email").value,

    theme: document.getElementById("theme").value,

    email_notifications:
        document.getElementById("email_notifications").checked ? 1 : 0,

    approval_notifications:
        document.getElementById("approval_notifications").checked ? 1 : 0,

    two_factor_auth:
        document.getElementById("two_factor_auth").checked ? 1 : 0,

    password_expiry:
        document.getElementById("password_expiry").checked ? 1 : 0,

    force_login_approval:
        document.getElementById("force_login_approval").checked ? 1 : 0

};

    const formData = new FormData();

    document
        .querySelectorAll(".settings-content input, .settings-content textarea, .settings-content select")
        .forEach(field => {

            if (!field.name)
                return;

            if (field.type === "checkbox") {

                formData.append(
                    field.name,
                    field.checked ? 1 : 0
                );

            }

            else {

                formData.append(
                    field.name,
                    field.value
                );

            }

        });

    try {

        const response = await fetch("api/settings/update.php", {

            method: "POST",

            body: formData

        });

        const result = await response.json();

        if (result.success) {

    showMessage(result.message, "success");
    loadBackups();
        applyTheme(document.getElementById("theme").value);

    // Apply the selected theme immediately
    const theme = document.getElementById("theme").value;
    applyTheme(theme);

}

        else {

            showMessage(result.message || "Save failed.", "error");

        }

    }

    catch (error) {

        console.error(error);

        showMessage("Server error.", "error");

    }

}


function showMessage(message, type = "success") {

    let toast = document.getElementById("settings-toast");

    if (!toast) {

        toast = document.createElement("div");

        toast.id = "settings-toast";

        document.body.appendChild(toast);

    }

    toast.className = type;

    toast.innerHTML = message;

    toast.style.display = "block";

    setTimeout(() => {

        toast.style.display = "none";

    }, 3000);

}
async function createBackup() {

    try {

        const response = await fetch("api/settings/backup.php");

        const result = await response.json();

        if(result.success){

    showMessage(result.message,"success");

    loadBackups();

}else {

            showMessage(result.message, "error");

        }

    } catch (error) {

        console.error(error);

        showMessage("Unable to create backup.", "error");

    }

}
function applyTheme(theme) {

    if(!theme){
        theme="light";
    }


    document.body.classList.remove(
        "light",
        "dark"
    );


    document.body.classList.add(theme);


    localStorage.setItem(
        "systemTheme",
        theme
    );

}
let auditLogs = [];

async function loadAuditLogs(){

    try{

        const response =
            await fetch(
                "api/settings/logs.php"
            );

        const result =
            await response.json();

        if(!result.success){

            return;

        }

        auditLogs=result.logs;

        renderAuditLogs(auditLogs);

    }

    catch(error){

        console.error(error);

    }

}

function renderAuditLogs(logs){

    const body=
        document.getElementById("auditBody");

    if(!body){

        return;

    }

    if(logs.length===0){

        body.innerHTML=`
            <tr>
                <td colspan="4">
                    No audit logs found.
                </td>
            </tr>
        `;

        return;

    }

    let html="";

    logs.forEach(log=>{

        html+=`

        <tr>

            <td>${log.created_at}</td>

            <td>${log.user_name ?? "System"}</td>

            <td>${log.entity_type}</td>

            <td>${log.action}</td>

        </tr>

        `;

    });

    body.innerHTML=html;

}
const search=document.getElementById("auditSearch");

if(search){

    search.addEventListener("input",function(){

        const term=this.value.toLowerCase();

        renderAuditLogs(

            auditLogs.filter(log=>

                (log.user_name ?? "")
                .toLowerCase()
                .includes(term)

                ||

                (log.action ?? "")
                .toLowerCase()
                .includes(term)

                ||

                (log.entity_type ?? "")
                .toLowerCase()
                .includes(term)

            )

        );

    });

}

async function loadAuditLogs(){

    try {

        const response =
            await fetch("api/settings/logs.php");

        const result =
            await response.json();


        if(!result.success){

            return;

        }


        const tbody =
            document.querySelector("#auditTable tbody");


        if(!tbody){

            return;

        }


        tbody.innerHTML="";


        result.logs.forEach(log=>{


           tbody.innerHTML += `

<tr>


<td>${log.created_at}</td>

<td>${log.first_name} ${log.last_name}</td>

<td>${log.entity_type}</td>

<td>${log.action}</td>


<td>

<button
class="download-btn"
onclick="downloadBackup('${backup.name}')">

<i class="fa fa-download"></i>

</button>

<button
class="delete-btn"
onclick="deleteBackup('${backup.name}')">

<i class="fa fa-trash"></i>

</button>

</td>

</tr>

`;

        });


    }

    catch(error){

        console.error(
            "Audit log error:",
            error
        );

    }

}
async function loadBackups(){

    const body = document.getElementById("backupBody");


    if(!body){

        console.log("Backup table not found");

        return;

    }


    try {


        const response = await fetch(
            "api/settings/backups.php"
        );


        const data = await response.json();


        console.log("Backup data:", data);



        body.innerHTML = "";



        if(!data.success || data.backups.length === 0){


            body.innerHTML = `

            <tr>

                <td colspan="4">
                    No backups available
                </td>

            </tr>

            `;


            return;

        }




        data.backups.forEach(file=>{


            body.innerHTML += `

            <tr>

                <td>

                    <i class="fa fa-file-archive"></i>

                    ${file.name}

                </td>


                <td>

                    ${file.size}

                </td>


                <td>

                    ${file.date}

                </td>


                <td>


                    <button
    class="action-btn"
    onclick="downloadBackup('${file.name}')">

    <i class="fa fa-download"></i>

</button>

<button
    class="action-btn delete"
    onclick="deleteBackup('${file.name}')">

    <i class="fa fa-trash"></i>

</button>


                </td>


            </tr>

            `;


        });


    }


    catch(error){


        console.error(
            "Backup loading error:",
            error
        );


        body.innerHTML = `

        <tr>

            <td colspan="4">

                Error loading backups

            </td>

        </tr>

        `;


    }


}
function downloadBackup(file){

    window.location =
        "api/settings/download_backup.php?file=" +
        encodeURIComponent(file);

}

async function deleteBackup(file){

    if(!confirm("Delete this backup?")){

        return;

    }

    const formData = new FormData();

    formData.append("file", file);

    const response = await fetch(
        "api/settings/delete_backup.php",
        {
            method:"POST",
            body:formData
        }
    );

    const result = await response.json();

    if(result.success){

        showMessage("Backup deleted.","success");

        loadBackups();

    }else{

        showMessage(result.message,"error");

    }

}