alert ("Welcome to the Tracker! Please log in or register to continue." );
const accountButton =
    document.getElementById("accountButton");

const authOverlay =
    document.getElementById("authOverlay");

const closeAuth =
    document.getElementById("closeAuth");

const loginSection =
    document.getElementById("loginSection");

const registerSection =
    document.getElementById("registerSection");

const showRegister =
    document.getElementById("showRegister");

const showLogin =
    document.getElementById("showLogin");

const loginForm =
    document.getElementById("loginForm");

const registerForm =
    document.getElementById("registerForm");

const authMessage =
    document.getElementById("authMessage");



/* =========================
   OPEN LOGIN WINDOW
========================= */

accountButton.addEventListener("click", () => {

    authOverlay.classList.add("active");

    showLoginForm();

});



/* =========================
   CLOSE LOGIN WINDOW
========================= */

closeAuth.addEventListener("click", () => {

    authOverlay.classList.remove("active");

});


/* Close when clicking outside */

authOverlay.addEventListener("click", (event) => {

    if (event.target === authOverlay) {

        authOverlay.classList.remove("active");

    }

});



/* =========================
   SWITCH TO REGISTER
========================= */

showRegister.addEventListener("click", () => {

    showRegisterForm();

});



/* =========================
   SWITCH TO LOGIN
========================= */

showLogin.addEventListener("click", () => {

    showLoginForm();

});



function showLoginForm() {

    loginSection.style.display = "block";

    registerSection.style.display = "none";

    authMessage.textContent = "";

    authMessage.style.color = "#ff7272";

}



function showRegisterForm() {

    loginSection.style.display = "none";

    registerSection.style.display = "block";

    authMessage.textContent = "";

    authMessage.style.color = "#ff7272";

}



/* =========================
   LOGIN
========================= */

loginForm.addEventListener("submit", async (event) => {

    event.preventDefault();


    const username =
        document.getElementById(
            "loginUsername"
        ).value;

    const password =
        document.getElementById(
            "loginPassword"
        ).value;


    try {

        const response = await fetch(
            "api/login.php",
            {
                method: "POST",

                headers: {
                    "Content-Type":
                        "application/json"
                },

                body: JSON.stringify({
                    username: username,
                    password: password
                })
            }
        );


        const data =
            await response.json();


        if (!response.ok) {

            authMessage.textContent =
                data.message ||
                "Invalid username or password.";

            return;

        }


        /* Login successful */

        accountButton.textContent =
            data.username;

        authOverlay.classList.remove(
            "active"
        );


    } catch (error) {

        authMessage.textContent =
            "Unable to connect to server.";

    }

});



/* =========================
   REGISTER
========================= */

registerForm.addEventListener(
    "submit",
    async (event) => {

        event.preventDefault();


        const username =
            document.getElementById(
                "registerUsername"
            ).value;


        const email =
            document.getElementById(
                "registerEmail"
            ).value;


        const password =
            document.getElementById(
                "registerPassword"
            ).value;


        try {

            const response = await fetch(
                "api/register.php",
                {
                    method: "POST",

                    headers: {
                        "Content-Type":
                            "application/json"
                    },

                    body: JSON.stringify({
                        username: username,
                        email: email,
                        password: password
                    })
                }
            );


            const data =
                await response.json();


            if (!response.ok) {

                authMessage.textContent =
                    data.message ||
                    "Registration failed.";

                return;

            }


            /* Successful registration */

            authMessage.style.color =
                "#7cff00";

            authMessage.textContent =
                "Account created successfully!";


            /*
             * After registering, switch
             * back to the login form.
             */

            setTimeout(() => {

                showLoginForm();

                document.getElementById(
                    "loginUsername"
                ).value = username;

            }, 1000);


        } catch (error) {

            authMessage.style.color =
                "#ff7272";

            authMessage.textContent =
                "Unable to connect to server.";

        }

    }
);