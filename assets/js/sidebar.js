const sidebar = document.querySelector(".sidebar");
const main = document.querySelector(".main");
const menuBtn = document.getElementById("menuBtn");

menuBtn.addEventListener("click", () => {

    if (window.innerWidth <= 768) {

        sidebar.classList.toggle("show");

    } else {

        sidebar.classList.toggle("collapsed");
        main.classList.toggle("sidebar-collapsed");
    }

    menuBtn.classList.toggle("active");

    localStorage.setItem(
        "sidebarCollapsed",
        sidebar.classList.contains("collapsed")
    );
});

window.addEventListener("load", () => {

    if (window.innerWidth > 768 &&
        localStorage.getItem("sidebarCollapsed") === "true") {

        sidebar.classList.add("collapsed");
        main.classList.add("sidebar-collapsed");
        menuBtn.classList.add("active");
    }
});