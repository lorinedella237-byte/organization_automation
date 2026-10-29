// Serialises the role permission matrix into the shape the save endpoint
// expects.
//
// The endpoint takes `grants` as a list of {ability, scope} objects, and an
// ungranted ability must be absent from that list rather than present with an
// empty scope. A plain HTML form cannot omit a field conditionally, so the page
// renders one select per ability and this builds the real payload on submit.
//
// Only selects with a chosen scope contribute. Anything left on "Not granted"
// is simply not sent, which is how the server is told to revoke it.

document.querySelectorAll('form[data-role-matrix]').forEach((form) => {
    form.addEventListener('submit', () => {
        // Clear anything added by a previous submit attempt, so a form that
        // failed validation and was resubmitted does not accumulate duplicates.
        form.querySelectorAll('[data-role-matrix-field]').forEach((node) => node.remove());

        let index = 0;

        form.querySelectorAll('select[data-ability]').forEach((select) => {
            if (select.value === '') {
                return;
            }

            [
                ['ability', select.dataset.ability],
                ['scope', select.value],
            ].forEach(([key, value]) => {
                const field = document.createElement('input');

                field.type = 'hidden';
                field.name = `grants[${index}][${key}]`;
                field.value = value;
                field.setAttribute('data-role-matrix-field', '');
                form.appendChild(field);
            });

            index += 1;
        });

        // `grants` must be present even when nothing is granted, so an empty
        // list is sent explicitly rather than omitting the key.
        if (index === 0) {
            const empty = document.createElement('input');

            empty.type = 'hidden';
            empty.name = 'grants';
            empty.value = '';
            empty.setAttribute('data-role-matrix-field', '');
            form.appendChild(empty);
        }
    });
});
