document.addEventListener("DOMContentLoaded", function () {

    const sidebar  = document.getElementById("rSidebar");
    const backdrop = document.getElementById("rBackdrop");

    function toggleDrawer(open) {
        if (!sidebar) return;
        sidebar.classList.toggle("show", open);
        backdrop.classList.toggle("show", open);
    }

    document.querySelectorAll("[data-drawer]").forEach(function (btn) {
        btn.addEventListener("click", function () {
            toggleDrawer(!sidebar.classList.contains("show"));
        });
    });
    if (backdrop) backdrop.addEventListener("click", function () { toggleDrawer(false); });

    // flash messages fade away on their own
    document.querySelectorAll(".alert:not(.alert-persist)").forEach(function (el) {
        setTimeout(function () {
            if (window.bootstrap && bootstrap.Alert) bootstrap.Alert.getOrCreateInstance(el).close();
        }, 5000);
    });

    // show the fee / requirements of the certificate picked on the request form
    const typeSelect = document.getElementById("certType");
    const typeInfo   = document.getElementById("certInfo");
    if (typeSelect && typeInfo) {
        const update = function () {
            const opt = typeSelect.options[typeSelect.selectedIndex];
            const fee = opt ? opt.getAttribute("data-fee") : null;
            const desc = opt ? opt.getAttribute("data-desc") : "";
            if (!opt || !opt.value) { typeInfo.textContent = ""; return; }
            typeInfo.textContent = "Fee: ₱" + Number(fee).toFixed(2) + (desc ? " — " + desc : "");
        };
        typeSelect.addEventListener("change", update);
        update();
    }
});
