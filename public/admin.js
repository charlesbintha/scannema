document.querySelectorAll("[data-payment-form]").forEach((form) => {
    const tickets = [...form.querySelectorAll("[data-ticket]")];
    const all = form.querySelector("[data-select-all]");
    const button = form.querySelector("[data-pay-button]");
    const count = form.querySelector("[data-selection-count]");
    if (!all || !button || !count) return;
    const update = () => {
        const selected = tickets.filter((ticket) => ticket.checked).length;
        count.textContent = String(selected);
        button.disabled = selected === 0;
        all.checked = tickets.length > 0 && selected === tickets.length;
        all.indeterminate = selected > 0 && selected < tickets.length;
    };
    all.addEventListener("change", () => {
        tickets.forEach((ticket) => {
            ticket.checked = all.checked;
        });
        update();
    });
    tickets.forEach((ticket) => ticket.addEventListener("change", update));
    form.addEventListener("submit", (event) => {
        if (!tickets.some((ticket) => ticket.checked)) {
            event.preventDefault();
            return;
        }
        button.disabled = true;
        button.textContent = "Enregistrement…";
    });
    update();
});
