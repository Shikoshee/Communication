// ======================================
// EXPORT FUNCTIONS
// ======================================

function exportExcel(){

    window.location.href =
    "api/reports/export-excel.php";

}


function exportPDF(){

    window.location.href =
    "api/reports/export-pdf.php";

}


// ======================================
// DATE FILTER
// ======================================

function applyFilter(){

    let start =
    document.getElementById("startDate").value;

    let end =
    document.getElementById("endDate").value;

    if(start === "" || end === ""){

        Swal.fire(
            "Missing Dates",
            "Please select both start and end dates.",
            "warning"
        );

        return;

    }

    window.location.href =
        "reports.php?start=" +
        encodeURIComponent(start) +
        "&end=" +
        encodeURIComponent(end);

}



// ======================================
// MONTHLY UPLOADS CHART
// ======================================

const monthNames = [

    "Jan",
    "Feb",
    "Mar",
    "Apr",
    "May",
    "Jun",
    "Jul",
    "Aug",
    "Sep",
    "Oct",
    "Nov",
    "Dec"

];

let uploadLabels = [];
let uploadValues = [];

uploadsData.forEach(item=>{

    uploadLabels.push(

        monthNames[item.month - 1]

    );

    uploadValues.push(

        Number(item.total)

    );

});

new Chart(

    document.getElementById(
        "uploadsChart"
    ),

    {

        type:"line",

        data:{

            labels:uploadLabels,

            datasets:[{

                label:"Uploads",

                data:uploadValues,

                borderColor:"#0B5ED7",

                backgroundColor:
                "rgba(11,94,215,.15)",

                fill:true,

                tension:.4

            }]

        },

        options:{

            responsive:true,

            plugins:{

                legend:{

                    display:true

                }

            }

        }

    }

);



// ======================================
// APPROVAL STATUS CHART
// ======================================

let approvalLabels = [];
let approvalValues = [];

approvalData.forEach(item=>{

    approvalLabels.push(

        item.status.charAt(0)
        .toUpperCase() +
        item.status.slice(1)

    );

    approvalValues.push(

        Number(item.total)

    );

});

new Chart(

    document.getElementById(
        "approvalChart"
    ),

    {

        type:"pie",

        data:{

            labels:approvalLabels,

            datasets:[{

                data:approvalValues,

                backgroundColor:[

                    "#198754",
                    "#F59E0B",
                    "#DC3545"

                ]

            }]

        },

        options:{

            responsive:true

        }

    }

);



// ======================================
// DEPARTMENT PERFORMANCE
// ======================================

let departmentLabels = [];
let departmentValues = [];

departmentData.forEach(item=>{

    departmentLabels.push(
        item.name
    );

    departmentValues.push(
        Number(item.total)
    );

});

new Chart(

    document.getElementById(
        "departmentPerformance"
    ),

    {

        type:"bar",

        data:{

            labels:departmentLabels,

            datasets:[{

                label:"Documents",

                data:departmentValues,

                backgroundColor:[

                    "#0B5ED7",
                    "#198754",
                    "#F59E0B",
                    "#DC3545",
                    "#6F42C1",
                    "#20C997",
                    "#FD7E14",
                    "#0DCAF0"

                ]

            }]

        },

        options:{

            responsive:true,

            plugins:{

                legend:{

                    display:false

                }

            },

            scales:{

                y:{

                    beginAtZero:true

                }

            }

        }

    }

);



// ======================================
// DOCUMENT TYPES
// ======================================

let typeLabels = [];
let typeValues = [];

documentTypeData.forEach(item=>{

    typeLabels.push(
        item.file_type
    );

    typeValues.push(
        Number(item.total)
    );

});

new Chart(

    document.getElementById(
        "documentTypes"
    ),

    {

        type:"doughnut",

        data:{

            labels:typeLabels,

            datasets:[{

                data:typeValues,

                backgroundColor:[

                    "#DC3545",
                    "#0B5ED7",
                    "#198754",
                    "#F59E0B",
                    "#6F42C1",
                    "#20C997",
                    "#FD7E14",
                    "#0DCAF0"

                ]

            }]

        },

        options:{

            responsive:true

        }

    }

);