document.addEventListener("DOMContentLoaded", () => {
    const root = document.documentElement;
    const button = document.createElement("button");
    button.type = "button";
    button.className = "kj-admin-theme-toggle";
    button.setAttribute("aria-label", "Changer de thème");

    const render = () => {
        const isDark = root.classList.contains("dark");
        button.setAttribute("aria-pressed", String(isDark));
        button.innerHTML = `
            <span class="kj-admin-theme-toggle__track">
                <span class="kj-admin-theme-toggle__thumb"></span>
            </span>
            <span class="kj-admin-theme-toggle__label">${isDark ? "Dark" : "Light"}</span>
        `;
    };

    button.addEventListener("click", () => {
        const nextTheme = root.classList.contains("dark") ? "light" : "dark";
        root.classList.toggle("dark", nextTheme === "dark");
        localStorage.setItem("kibarejob-theme", nextTheme);
        render();
    });

    render();
    document.body.appendChild(button);
});
