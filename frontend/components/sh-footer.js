// <sh-footer></sh-footer>
class ShFooter extends HTMLElement {
  connectedCallback() {
    this.innerHTML = `
      <footer class="gradient-navy text-slate-300 mt-20">
        <div class="max-w-7xl mx-auto px-6 py-12 grid md:grid-cols-4 gap-8">
          <div>
            <div class="text-white font-display font-bold mb-3">SmartHire AI</div>
            <p class="text-sm text-slate-400">Plateforme de recrutement propulsée par l'IA et le NLP.</p>
          </div>
          <div><h4 class="text-white font-semibold mb-3 text-sm">Produit</h4>
            <ul class="space-y-2 text-sm">
              <li><a href="jobs.html" class="hover:text-gold">Offres</a></li>
              <li><a href="recruiter.html" class="hover:text-gold">Recruteurs</a></li>
              <li><a href="candidate.html" class="hover:text-gold">Candidats</a></li>
              <li><a href="admin.html" class="hover:text-gold">Admin</a></li>
            </ul></div>
          <div><h4 class="text-white font-semibold mb-3 text-sm">Entreprise</h4>
            <ul class="space-y-2 text-sm"><li>À propos</li><li>Carrières</li><li>Contact</li></ul></div>
          <div><h4 class="text-white font-semibold mb-3 text-sm">Ressources</h4>
            <ul class="space-y-2 text-sm"><li>Documentation</li><li>Aide</li><li>Confidentialité</li></ul></div>
        </div>
        <div class="border-t border-white/10 py-4 text-center text-xs text-slate-500">© 2026 SmartHire AI</div>
      </footer>`;
  }
}
customElements.define('sh-footer', ShFooter);
