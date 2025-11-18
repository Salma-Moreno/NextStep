
    function togglePassword(id) {
        const input = document.getElementById(id);
        input.type = input.type === "password" ? "text" : "password";
    }

    function validatePasswords() {
        const password = document.getElementById("password").value;
        const confirmPassword = document.getElementById("confirm-password").value;
        const errorMessage = document.getElementById("password-error");
        const saveButton = document.getElementById("save-btn");

        if (password === "" || confirmPassword === "") {
            errorMessage.textContent = "";
            errorMessage.style.display = "none";
            saveButton.disabled = true;
            return;
        }

        if (password !== confirmPassword) {
            errorMessage.textContent = "Las contraseñas no coinciden.";
            errorMessage.style.display = "block";
            saveButton.disabled = true;
        } else {
            errorMessage.style.display = "none";
            saveButton.disabled = false;
        }
    }
