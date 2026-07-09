// <sh-navbar active="jobs"></sh-navbar>
// Barre de navigation publique partagée (Accueil / Offres / Recruteurs / Candidats).
// Ne definit pas de shadow DOM afin de rester compatible avec les classes Tailwind globales.
class ShNavbar extends HTMLElement {
  static links = [
    { key: 'index', href: 'index.html', label: 'Accueil' },
    { key: 'jobs', href: 'jobs.html', label: 'Offres' },
    { key: 'recruiter', href: 'recruiter.html', label: 'Recruteurs' },
    { key: 'candidate', href: 'candidate.html', label: 'Candidats' },
  ];

  connectedCallback() {
    const active = this.getAttribute('active') || 'index';
    const navLinks = ShNavbar.links.map(l => {
      const cls = l.key === active
        ? 'text-gold text-sm font-medium'
        : 'text-slate-200 hover:text-gold text-sm font-medium';
      return `<a href="${l.href}" class="${cls}">${l.label}</a>`;
    }).join('');

    this.innerHTML = `
      <header class="gradient-navy">
        <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
          <a href="index.html" class="flex items-center gap-2 text-white font-display font-bold text-lg">
            <span class="h-8 w-8 rounded-lg gradient-gold flex items-center justify-center text-navy">S</span>
            SmartHire <span class="text-gold">AI</span>
          </a>
          <nav class="hidden md:flex items-center gap-6">${navLinks}</nav>
          <div class="flex gap-2">
            <a href="login.html" class="text-slate-200 text-sm font-medium px-3 py-2">Se connecter</a>
            <a href="register.html" class="btn-gold text-sm">S'inscrire</a>
          </div>
        </div>
      </header>`;
  }
}
customElements.define('sh-navbar', ShNavbar);
