export default (() => {
    const initTogglesCredentials = () => {
        const adminSelect = document.getElementById("type");
        const permissionsToggles = [
            ...document.querySelectorAll(
                `input[type="checkbox"][name^="permissions"]:not([disabled])`
            )
        ];

        permissionsToggles.map(toggle => {
            toggle.addEventListener("change", event => {
                if (!event.currentTarget.checked) {
                    adminSelect && (adminSelect.value = "analytics");
                }

                if (
                    "R" === event.currentTarget.value &&
                    !event.currentTarget.checked
                ) {
                    document.getElementById(
                        `permissions[${event.currentTarget.dataset.entity}][]-W`
                    ).checked = false;
                }
                if (
                    "W" === event.currentTarget.value &&
                    event.currentTarget.checked
                ) {
                    document.getElementById(
                        `permissions[${event.currentTarget.dataset.entity}][]-R`
                    ).checked = true;
                }
            });
        });

        adminSelect &&
            adminSelect.addEventListener("change", event => {
                permissionsToggles.map(toggle => {
                    const isAdminCredentials = event.target.value === "admin";
                    toggle.disabled = isAdminCredentials;
                    isAdminCredentials
                        ? toggle.setAttribute("checked", true)
                        : toggle.removeAttribute("checked");
                });
            });
    };

    const init = () => {
        initTogglesCredentials();
    };

    return { init };
})();
