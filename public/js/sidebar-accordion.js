const getSidebarGroupLabels = () =>
    Array.from(document.querySelectorAll('.fi-sidebar-group[data-group-label]'))
        .map((group_element) => group_element.dataset.groupLabel)
        .filter((group_label) => typeof group_label === 'string' && group_label !== '');

const normalizeCollapsedGroups = (collapsed_groups) => {
    if (collapsed_groups === null || collapsed_groups === 'null') {
        return [];
    }

    return collapsed_groups;
};

const patchSidebarAccordion = () => {
    const sidebar_store = window.Alpine?.store('sidebar');

    if (!sidebar_store || sidebar_store.__accordionPatched) {
        return;
    }

    const original_toggle_collapsed_group =
        sidebar_store.toggleCollapsedGroup.bind(sidebar_store);

    sidebar_store.toggleCollapsedGroup = function toggleCollapsedGroupAccordion(group) {
        this.collapsedGroups = normalizeCollapsedGroups(this.collapsedGroups);

        if (this.collapsedGroups.includes(group)) {
            const all_group_labels = getSidebarGroupLabels();

            this.collapsedGroups = all_group_labels.filter(
                (group_label) => group_label !== group,
            );

            return;
        }

        original_toggle_collapsed_group(group);
    };

    sidebar_store.__accordionPatched = true;
};

document.addEventListener('alpine:initialized', patchSidebarAccordion);
document.addEventListener('livewire:navigated', patchSidebarAccordion);
