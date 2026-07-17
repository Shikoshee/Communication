/* ==========================================
   COMMUNICATION SYSTEM - DASHBOARD JS
==========================================*/
document.addEventListener("DOMContentLoaded", function(){
/* ==========================================
   SIDEBAR TOGGLE
==========================================*/
const menuBtn = document.getElementById("menuBtn");
const sidebar = document.querySelector(".sidebar");
const main = document.querySelector(".main");
if(menuBtn){
    menuBtn.addEventListener("click", function(){
        // Desktop collapse
        if(window.innerWidth > 768){
            sidebar.classList.toggle("collapsed");
            main.classList.toggle("expanded");
        }
        // Mobile slide menu
        else{
            sidebar.classList.toggle("active");
        }
    });
}
/* ==========================================
   ACTIVE MENU HIGHLIGHT
==========================================*/
const currentPage = window.location.pathname.split("/").pop();
const menuLinks = document.querySelectorAll(
    ".sidebar ul li a"
);
menuLinks.forEach(function(link){
    const linkPage = link
        .getAttribute("href");
    if(linkPage === currentPage){
        link.parentElement.classList.add("active");
    }
});
/* ==========================================
   CHART JS - DEPARTMENT ACTIVITY
==========================================*/
const chartElement = document.getElementById(
    "departmentChart"
);
if(chartElement){
    const departmentChart = new Chart(
        chartElement,
        {
        type:"bar",
        data:{
            labels:[
                "Finance",
                "HR",
                "IT",
                "Marketing",
                "Operations"

            ],
            datasets:[{
                label:"Documents Uploaded",
                data:[35,55,42,28,60],
                backgroundColor:[
                    "#0B5ED7",
                    "#198754",
                    "#F59E0B",
                    "#DC3545",
                    "#6610f2"
                ],
                borderRadius:8
            }]
        },
        options:{
            responsive:true,
            plugins:{
                legend:{
                    display:true
                }
            },
            scales:{
                y:{
                    beginAtZero:true
                }
            }
        }
    });
}

/* ==========================================
   NOTIFICATION EXAMPLE
==========================================*/
const notification =
document.querySelector(".fa-bell");
if(notification){
    notification.addEventListener(
        "click",
        function(){
        Swal.fire({
            title:"Notifications",
            html:`
            <p>
            <b>3</b> new documents require approval.
            </p>
            <p>
            5 files were shared with your department.
            </p>
            `,
            icon:"info",
            confirmButtonColor:"#0B5ED7"
        });
    });
}
/* ==========================================
   SEARCH FUNCTION
==========================================*/
const searchInput =
document.querySelector(".search input");
if(searchInput){
    searchInput.addEventListener(
        "keyup",
        function(){
        let searchValue =
        searchInput.value.toLowerCase();
        let rows =
        document.querySelectorAll(
            "table tbody tr"
        );
        rows.forEach(function(row){
            let text =
            row.innerText.toLowerCase();
            if(text.includes(searchValue)){
                row.style.display="";
            }
            else{
                row.style.display="none";
            }
        });
    });
}
/* ==========================================
   FUTURE AJAX PLACEHOLDER
   PHP API CONNECTION WILL GO HERE
==========================================*/
function loadDashboardData(){
    /*
    Future PHP example:
    fetch("api/dashboard.php")
    .then(response => response.json())
    .then(data => {
        console.log(data);
    });
    */
    console.log(
        "Dashboard ready for backend connection"
    );
}
loadDashboardData();
});

/* ==========================
   CURRENT DATE
========================== */

const dateElement = document.getElementById("currentDate");

if(dateElement){

    const today = new Date();

    dateElement.innerHTML =
        today.toLocaleDateString("en-GB",{

            weekday:"long",
            day:"numeric",
            month:"long",
            year:"numeric"

        });

}