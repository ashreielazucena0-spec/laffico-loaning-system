document.addEventListener("DOMContentLoaded", function () {

    const sidebar = document.getElementById("sidebar");
    const toggle = document.getElementById("sidebarToggle");
    const backdrop = document.getElementById("sidebarBackdrop");

    function closeSidebar() {
        sidebar && sidebar.classList.remove("show");
        backdrop && backdrop.classList.remove("show");
    }

    if (toggle) {
        toggle.addEventListener("click", function () {
            sidebar.classList.toggle("show");
            backdrop.classList.toggle("show");
        });
    }

    if (backdrop) {
        backdrop.addEventListener("click", closeSidebar);
    }

    // auto-dismiss flash alerts after a few seconds
    document.querySelectorAll(".alert:not(.alert-persist)").forEach(function (el) {
        setTimeout(function () {
            if (window.bootstrap && bootstrap.Alert) {
                bootstrap.Alert.getOrCreateInstance(el).close();
            }
        }, 4000);
    });

    // Live search: filter the table rows as soon as the user types,
    // instead of waiting for Enter / a page reload.
    document.querySelectorAll(".search-box input[name='q']").forEach(function (input) {
        const form = input.closest("form");
        const card = input.closest(".content-card");
        const table = card ? card.querySelector("table") : null;
        if (!table) return;

        const tbody = table.querySelector("tbody");
        const rows = Array.from(tbody.querySelectorAll("tr"));

        // Row shown when there are no matches.
        const noResultsRow = document.createElement("tr");
        noResultsRow.className = "live-search-empty";
        const td = document.createElement("td");
        td.colSpan = table.querySelectorAll("thead th").length;
        td.className = "text-center text-muted py-4";
        td.textContent = "No matching records found.";
        noResultsRow.appendChild(td);
        noResultsRow.style.display = "none";
        tbody.appendChild(noResultsRow);

        // Filtering already happens live — don't let Enter reload the page.
        if (form) {
            form.addEventListener("submit", function (e) {
                e.preventDefault();
            });
        }

        input.addEventListener("input", function () {
            const needle = input.value.trim().toLowerCase();
            let visible = 0;

            rows.forEach(function (row) {
                const match = needle === "" || row.textContent.toLowerCase().includes(needle);
                row.style.display = match ? "" : "none";
                if (match) visible++;
            });

            noResultsRow.style.display = visible === 0 ? "" : "none";
        });
    });

    // Searchable comboboxes: turn every <select class="form-select"> into a
    // type-to-filter dropdown instead of a long list you have to scroll.
    initSearchableSelects();

    // Fields that only apply in some cases (e.g. Floor when "Living in a building?" = Yes)
    initShowWhen();

    // "Copy" buttons (resident login details)
    document.querySelectorAll("[data-copy]").forEach(function (btn) {
        btn.addEventListener("click", function () {
            const text = btn.getAttribute("data-copy");
            const done = function () { btn.innerHTML = '<i class="bi bi-check2"></i> Copied'; };
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(done);
            } else {
                const ta = document.createElement("textarea");
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand("copy");
                ta.remove();
                done();
            }
        });
    });

});

/*
 * <div data-show-when="household_id=NEW&hh_in_building=Yes"> ... </div>
 * is shown (and submitted) only while every name=value pair matches
 * a field in the same form.
 */
function initShowWhen() {
    document.querySelectorAll("[data-show-when]").forEach(function (box) {
        const form = box.closest("form");
        if (!form) return;

        const conds = box.getAttribute("data-show-when").split("&").map(function (pair) {
            return pair.split("=");
        });

        function evaluate() {
            const ok = conds.every(function (c) {
                const el = form.elements[c[0]];
                return el && el.value === c[1];
            });
            box.classList.toggle("d-none", !ok);
            box.querySelectorAll("input, select, textarea").forEach(function (el) {
                el.disabled = !ok; // hidden fields are not submitted
            });
        }

        conds.forEach(function (c) {
            const el = form.elements[c[0]];
            if (el) el.addEventListener("change", evaluate);
        });
        evaluate();
    });
}

function initSearchableSelects() {

    document.querySelectorAll("select.form-select").forEach(function (select) {
        if (select.dataset.searchable === "false") return;
        enhanceSearchableSelect(select);
    });

    // Enforce "required" on the visually-hidden native <select> ourselves,
    // since form.reportValidity() can behave inconsistently on elements
    // that are hidden off-screen rather than removed from the DOM.
    document.querySelectorAll("form").forEach(function (form) {
        form.addEventListener("submit", function (e) {
            let firstInvalid = null;

            form.querySelectorAll(".ss-native[required]").forEach(function (select) {
                const combo = select.closest(".ss-combo");
                if (!combo) return;
                const control = combo.querySelector(".ss-control");

                if (!select.value) {
                    control.classList.add("is-invalid");
                    if (!firstInvalid) firstInvalid = combo;
                } else {
                    control.classList.remove("is-invalid");
                }
            });

            if (firstInvalid) {
                e.preventDefault();
                openSsCombo(firstInvalid);
                firstInvalid.scrollIntoView({ block: "center", behavior: "smooth" });
            }
        });
    });
}

function openSsCombo(combo) {
    const control = combo.querySelector(".ss-control");
    if (control) control.click();
}

function enhanceSearchableSelect(select) {
    if (select.dataset.ssEnhanced) return;
    select.dataset.ssEnhanced = "1";

    const wrapper = document.createElement("div");
    wrapper.className = "ss-combo";
    select.parentNode.insertBefore(wrapper, select);

    select.classList.add("ss-native", "visually-hidden");
    select.setAttribute("tabindex", "-1");
    select.setAttribute("aria-hidden", "true");
    wrapper.appendChild(select);

    const control = document.createElement("button");
    control.type = "button";
    control.className = "form-select ss-control text-start";
    control.setAttribute("aria-haspopup", "listbox");
    control.setAttribute("aria-expanded", "false");
    if (select.disabled) control.disabled = true;

    const valueSpan = document.createElement("span");
    valueSpan.className = "ss-value";
    control.appendChild(valueSpan);
    wrapper.appendChild(control);

    const dropdown = document.createElement("div");
    dropdown.className = "ss-dropdown";

    const searchWrap = document.createElement("div");
    searchWrap.className = "ss-search-wrap";
    const searchInput = document.createElement("input");
    searchInput.type = "text";
    searchInput.className = "form-control form-control-sm ss-search";
    searchInput.placeholder = "Type to search...";
    searchInput.autocomplete = "off";
    searchWrap.appendChild(searchInput);
    dropdown.appendChild(searchWrap);

    const list = document.createElement("ul");
    list.className = "ss-options";
    list.setAttribute("role", "listbox");
    dropdown.appendChild(list);

    const emptyMsg = document.createElement("div");
    emptyMsg.className = "ss-empty";
    emptyMsg.textContent = "No matches found.";
    dropdown.appendChild(emptyMsg);

    wrapper.appendChild(dropdown);

    function buildOptions() {
        list.innerHTML = "";
        Array.from(select.options).forEach(function (opt) {
            if (opt.value === "") return; // skip the "Select..." placeholder
            const li = document.createElement("li");
            li.className = "ss-option";
            li.setAttribute("role", "option");
            li.dataset.value = opt.value;
            li.textContent = opt.textContent;
            if (opt.selected) li.classList.add("ss-selected");
            list.appendChild(li);
        });
    }
    buildOptions();

    function syncLabel() {
        const selectedOpt = select.options[select.selectedIndex];
        if (selectedOpt && selectedOpt.value !== "") {
            valueSpan.textContent = selectedOpt.textContent;
            valueSpan.classList.remove("text-muted");
        } else {
            valueSpan.textContent = (select.options[0] && select.options[0].value === "")
                ? select.options[0].textContent
                : "Select...";
            valueSpan.classList.add("text-muted");
        }
    }
    syncLabel();

    function closeDropdown() {
        dropdown.classList.remove("show");
        control.setAttribute("aria-expanded", "false");
    }

    function filterOptions(query) {
        const q = query.trim().toLowerCase();
        let visibleCount = 0;
        let firstVisible = null;

        Array.from(list.children).forEach(function (li) {
            const match = q === "" || li.textContent.toLowerCase().includes(q);
            li.style.display = match ? "" : "none";
            li.classList.remove("ss-highlight");
            if (match) {
                visibleCount++;
                if (!firstVisible) firstVisible = li;
            }
        });

        if (firstVisible) firstVisible.classList.add("ss-highlight");
        dropdown.classList.toggle("ss-no-results", visibleCount === 0);
    }

    function openDropdown() {
        if (control.disabled) return;

        document.querySelectorAll(".ss-dropdown.show").forEach(function (d) {
            if (d !== dropdown) d.classList.remove("show");
        });
        document.querySelectorAll('.ss-control[aria-expanded="true"]').forEach(function (c) {
            if (c !== control) c.setAttribute("aria-expanded", "false");
        });

        dropdown.classList.add("show");
        control.setAttribute("aria-expanded", "true");
        searchInput.value = "";
        filterOptions("");
        window.setTimeout(function () { searchInput.focus(); }, 0);
    }

    function selectOption(li) {
        select.value = li.dataset.value;
        select.dispatchEvent(new Event("change", { bubbles: true }));
        syncLabel();
        control.classList.remove("is-invalid");
        Array.from(list.children).forEach(function (item) {
            item.classList.toggle("ss-selected", item === li);
        });
        closeDropdown();
        control.focus();
    }

    control.addEventListener("click", function () {
        if (dropdown.classList.contains("show")) {
            closeDropdown();
        } else {
            openDropdown();
        }
    });

    control.addEventListener("keydown", function (e) {
        if (e.key === "Enter" || e.key === " " || e.key === "ArrowDown") {
            e.preventDefault();
            openDropdown();
        }
    });

    searchInput.addEventListener("input", function () {
        filterOptions(searchInput.value);
    });

    searchInput.addEventListener("keydown", function (e) {
        const visible = Array.from(list.children).filter(function (li) {
            return li.style.display !== "none";
        });
        let idx = visible.findIndex(function (li) {
            return li.classList.contains("ss-highlight");
        });

        if (e.key === "ArrowDown") {
            e.preventDefault();
            if (!visible.length) return;
            if (idx >= 0) visible[idx].classList.remove("ss-highlight");
            idx = (idx + 1) % visible.length;
            visible[idx].classList.add("ss-highlight");
            visible[idx].scrollIntoView({ block: "nearest" });
        } else if (e.key === "ArrowUp") {
            e.preventDefault();
            if (!visible.length) return;
            if (idx >= 0) visible[idx].classList.remove("ss-highlight");
            idx = (idx - 1 + visible.length) % visible.length;
            visible[idx].classList.add("ss-highlight");
            visible[idx].scrollIntoView({ block: "nearest" });
        } else if (e.key === "Enter") {
            e.preventDefault();
            if (idx >= 0) selectOption(visible[idx]);
        } else if (e.key === "Escape") {
            e.preventDefault();
            closeDropdown();
            control.focus();
        } else if (e.key === "Tab") {
            closeDropdown();
        }
    });

    list.addEventListener("click", function (e) {
        const li = e.target.closest(".ss-option");
        if (li) selectOption(li);
    });

    document.addEventListener("click", function (e) {
        if (!wrapper.contains(e.target)) closeDropdown();
    });

    // Allow other code (e.g. a form reset) to ask us to re-read the
    // native select's current options/value and refresh the UI.
    select.addEventListener("ss-refresh", function () {
        buildOptions();
        syncLabel();
    });
}