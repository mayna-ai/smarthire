// <sh-sidebar role="candidate|recruiter|admin" active="candidate-cv"></sh-sidebar>
// Barre laterale des tableaux de bord. Le lien correspondant a `active` est mis en surbrillance.
class ShSidebar extends HTMLElement {
  static roles = {
    candidate: {
      user: { initials: 'JD', name: 'Jane Doe', subtitle: 'Candidate' },
      links: [
        { key: 'candidate', href: 'candidate.html', icon: '🏠', label: 'Tableau de bord' },
        { key: 'candidate-cv', href: 'candidate-cv.html', icon: '📄', label: 'Mon CV' },
        { key: 'candidate-profile', href: 'candidate-profile.html', icon: '👤', label: 'Mon profil' },
        { key: 'candidate-recommendations', href: 'candidate-recommendations.html', icon: '✨', label: 'Recommandations' },
        { key: 'candidate-applications', href: 'candidate-applications.html', icon: '📨', label: 'Mes candidatures' },
        { key: 'candidate-notifications', href: 'candidate-notifications.html', icon: '🔔', label: 'Notifications' },
        { key: 'candidate-settings', href: 'candidate-settings.html', icon: '⚙️', label: 'Paramètres' },
      ],
    },
    recruiter: {
      user: { initials: 'MW', name: 'Marcus Weber', subtitle: 'Recruiter · Northwind' },
      links: [
        { key: 'recruiter', href: 'recruiter.html', icon: '🏠', label: 'Tableau de bord' },
        { key: 'recruiter-jobs', href: 'recruiter-jobs.html', icon: '💼', label: "Offres d'emploi" },
        { key: 'recruiter-candidates', href: 'recruiter-candidates.html', icon: '👥', label: 'Candidats' },
        { key: 'recruiter-ranking', href: 'recruiter-ranking.html', icon: '📊', label: 'Classement IA' },
        { key: 'recruiter-analytics', href: 'recruiter-analytics.html', icon: '📈', label: 'Analyses' },
        { key: 'recruiter-settings', href: 'recruiter-settings.html', icon: '⚙️', label: 'Paramètres' },
      ],
    },
    admin: {
      user: { initials: 'AD', name: 'Admin', subtitle: 'Super Admin' },
      links: [
        { key: 'admin', href: 'admin.html', icon: '🏠', label: 'Tableau de bord' },
        { key: 'admin-users', href: 'admin.html', icon: '👥', label: 'Utilisateurs' },
        { key: 'admin-companies', href: 'admin.html', icon: '🏢', label: 'Entreprises' },
        { key: 'admin-jobs', href: 'admin.html', icon: '💼', label: 'Offres' },
        { key: 'admin-moderation', href: 'admin.html', icon: '🛡', label: 'Modération' },
        { key: 'admin-settings', href: 'admin.html', icon: '⚙️', label: 'Paramètres' },
      ],
    },
  };

  connectedCallback() {
    const role = this.getAttribute('role') || 'candidate';
    const active = this.getAttribute('active') || role;
    const cfg = ShSidebar.roles[role];
    if (!cfg) { console.error(`sh-sidebar: role inconnu "${role}"`); return; }

    const navLinks = cfg.links.map(l => {
      const isActive = l.key === active;
      const cls = isActive
        ? 'flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium bg-navy text-white'
        : 'flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-100';
      return `<a href="${l.href}" class="${cls}"><span>${l.icon}</span>${l.label}</a>`;
    }).join('');

    this.innerHTML = `
      <aside class="w-64 bg-white border-r border-line hidden lg:flex flex-col">
        <div class="p-5 border-b border-line flex items-center gap-2 font-display font-bold">
          <span class="h-8 w-8 rounded-lg gradient-gold flex items-center justify-center text-navy">S</span>
          SmartHire <span class="text-gold">AI</span>
        </div>
        <nav class="p-3 space-y-1 flex-1">${navLinks}</nav>
        <div class="p-4 border-t border-line flex items-center gap-3">
          <div class="h-9 w-9 rounded-full bg-navy text-white flex items-center justify-center font-semibold text-sm">${cfg.user.initials}</div>
          <div class="text-sm"><p class="font-semibold">${cfg.user.name}</p><p class="text-xs text-muted">${cfg.user.subtitle}</p></div>
        </div>
      </aside>`;
  }
}
customElements.define('sh-sidebar', ShSidebar);
