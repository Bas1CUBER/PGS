Swal.fire({
                icon: "error",
                title: "Access Denied",
                text: "YOU DON\'T HAVE ACCESS TO THIS PAGE",
                confirmButtonColor: "#d33"
            }).then(() => {
                window.location.href = "employee_dashboard.php";
            });
