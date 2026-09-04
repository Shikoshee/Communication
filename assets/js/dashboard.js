document.addEventListener("DOMContentLoaded", function () {

    /*
    ==========================================================
    DASHBOARD CHARTS
    ==========================================================
    */

    if (
        typeof Chart === "undefined" ||
        typeof window.dashboardChartData === "undefined"
    ) {

        return;

    }


    const chartData =
        window.dashboardChartData;


    /*
    ==========================================================
    DOCUMENTS BY DEPARTMENT
    ==========================================================
    */

    const departmentCanvas =
        document.getElementById(
            "departmentChart"
        );


    if(departmentCanvas){

        const departmentLabels =
            Array.isArray(
                chartData.departmentLabels
            )
                ? chartData.departmentLabels
                : [];


        const departmentValues =
            Array.isArray(
                chartData.departmentValues
            )
                ? chartData.departmentValues
                : [];


        new Chart(

            departmentCanvas,

            {

                type: "bar",


                data: {

                    labels:
                        departmentLabels,


                    datasets: [

                        {

                            label:
                                "Documents",


                            data:
                                departmentValues,


                            backgroundColor:
                                "#2563eb",


                            borderColor:
                                "#1d4ed8",


                            borderWidth:
                                1,


                            borderRadius:
                                6

                        }

                    ]

                },


                options: {

                    responsive:
                        true,


                    maintainAspectRatio:
                        false,


                    plugins: {

                        legend: {

                            display:
                                false

                        },


                        tooltip: {

                            callbacks: {

                                label:
                                    function(context){

                                        return (
                                            " Documents: " +
                                            context.raw
                                        );

                                    }

                            }

                        }

                    },


                    scales: {

                        y: {

                            beginAtZero:
                                true,


                            ticks: {

                                precision:
                                    0

                            }

                        }

                    }

                }

            }

        );

    }


    /*
    ==========================================================
    DOCUMENT STATUS
    ==========================================================
    */

    const statusCanvas =
        document.getElementById(
            "statusChart"
        );


    if(statusCanvas){

        const statusLabels =
            Array.isArray(
                chartData.statusLabels
            )
                ? chartData.statusLabels
                : [];


        const statusValues =
            Array.isArray(
                chartData.statusValues
            )
                ? chartData.statusValues
                : [];


        /*
         * Determine colors from actual status names.
         */

        const statusColors =
            statusLabels.map(
                function(status){

                    const value =
                        String(
                            status
                        ).toLowerCase();


                    if(
                        value === "approved"
                    ){

                        return "#16a34a";

                    }


                    if(
                        value === "pending"
                    ){

                        return "#f59e0b";

                    }


                    if(
                        value === "rejected"
                    ){

                        return "#dc2626";

                    }


                    return "#64748b";

                }
            );


        /*
         * If there is no data, use a neutral color.
         */

        const colors =
            statusColors.length > 0
                ? statusColors
                : ["#cbd5e1"];


        const values =
            statusValues.length > 0
                ? statusValues
                : [0];


        const labels =
            statusLabels.length > 0
                ? statusLabels
                : ["No data"];


        new Chart(

            statusCanvas,

            {

                type:
                    "doughnut",


                data: {

                    labels:
                        labels,


                    datasets: [

                        {

                            label:
                                "Documents",


                            data:
                                values,


                            backgroundColor:
                                colors,


                            borderColor:
                                "#ffffff",


                            borderWidth:
                                2,


                            hoverOffset:
                                6

                        }

                    ]

                },


                options: {

                    responsive:
                        true,


                    maintainAspectRatio:
                        false,


                    cutout:
                        "65%",


                    plugins: {

                        legend: {

                            position:
                                "bottom"

                        },


                        tooltip: {

                            callbacks: {

                                label:
                                    function(context){

                                        return (
                                            " " +
                                            context.label +
                                            ": " +
                                            context.raw
                                        );

                                    }

                            }

                        }

                    }

                }

            }

        );

    }


    /*
    ==========================================================
    MONTHLY UPLOADS
    ==========================================================
    */

    const uploadCanvas =
        document.getElementById(
            "uploadChart"
        );


    if(uploadCanvas){

        const uploadLabels =
            Array.isArray(
                chartData.uploadLabels
            )
                ? chartData.uploadLabels
                : [];


        const uploadValues =
            Array.isArray(
                chartData.uploadValues
            )
                ? chartData.uploadValues
                : [];


        new Chart(

            uploadCanvas,

            {

                type:
                    "line",


                data: {

                    labels:
                        uploadLabels,


                    datasets: [

                        {

                            label:
                                "Uploads",


                            data:
                                uploadValues,


                            borderColor:
                                "#7c3aed",


                            backgroundColor:
                                "rgba(124, 58, 237, 0.15)",


                            borderWidth:
                                3,


                            pointBackgroundColor:
                                "#7c3aed",


                            pointBorderColor:
                                "#ffffff",


                            pointBorderWidth:
                                2,


                            pointRadius:
                                4,


                            pointHoverRadius:
                                6,


                            tension:
                                0.4,


                            fill:
                                true

                        }

                    ]

                },


                options: {

                    responsive:
                        true,


                    maintainAspectRatio:
                        false,


                    plugins: {

                        legend: {

                            display:
                                false

                        },


                        tooltip: {

                            callbacks: {

                                label:
                                    function(context){

                                        return (
                                            " Uploads: " +
                                            context.raw
                                        );

                                    }

                            }

                        }

                    },


                    scales: {

                        y: {

                            beginAtZero:
                                true,


                            ticks: {

                                precision:
                                    0

                            }

                        }

                    }

                }

            }

        );

    }

});