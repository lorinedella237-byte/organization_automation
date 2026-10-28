// One quiet entrance for public pages: content settles into place as it is
// reached, rather than arriving all at once.
//
// Content is visible by default in CSS. The `reveal-ready` class is only added
// once this script has confirmed it can observe and release the elements, so a
// page with JavaScript disabled, or one served to a crawler, still renders
// everything. Reduced-motion preferences are honoured in the stylesheet rather
// than here, so the preference is respected even if it changes after load.

const elements = document.querySelectorAll('.js-reveal');

if (elements.length > 0 && 'IntersectionObserver' in window) {
    document.documentElement.classList.add('reveal-ready');

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        },
        { rootMargin: '0px 0px -8% 0px', threshold: 0.04 }
    );

    elements.forEach((element, index) => {
        // Stagger within a group of siblings, capped so a long list never
        // leaves the last item waiting.
        const position = Number(element.dataset.revealIndex ?? index % 4);

        element.style.setProperty('--reveal-delay', `${Math.min(position, 3) * 70}ms`);
        observer.observe(element);
    });
}
