const scrollActiveSidebarItemIntoView = () => {
    const sidebar = document.querySelector('aside.fi-sidebar');
    if (!sidebar) {
        return;
    }

    // item attivo: usa la classe che vedo nel tuo markup
    const activeItem = sidebar.querySelector('.fi-sidebar-item.fi-active');
    if (!activeItem) {
        return;
    }

    // lascia che sia il browser a scegliere il contenitore scrollabile
    activeItem.scrollIntoView({
        block: 'center',
        inline: 'nearest',
        behavior: 'smooth',
    });
};

// scroll sul primo load completo della pagina
window.addEventListener('load', () => {
    scrollActiveSidebarItemIntoView();
});

// scroll dopo ogni navigazione SPA di Livewire (wire:navigate)
document.addEventListener('livewire:navigated', () => {
    // piccolo ritardo per permettere a Filament di aggiornare le classi (fi-active)
    setTimeout(() => {
        scrollActiveSidebarItemIntoView();
    }, 50);
});