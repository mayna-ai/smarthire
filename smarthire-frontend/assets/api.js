// assets/api.js
// Client API partagé pour toutes les pages du frontend SmartHire AI.
// Centralise l'URL de base, la gestion du token JWT et les appels fetch.

const API_BASE_URL = 'http://localhost/smarthire-api'; // à adapter selon ton dossier XAMPP

const SmartHireAPI = {
  getToken() {
    return localStorage.getItem('sh_token');
  },

  getRefreshToken() {
    return localStorage.getItem('sh_refresh_token');
  },

  getUser() {
    const raw = localStorage.getItem('sh_user');
    return raw ? JSON.parse(raw) : null;
  },

  saveSession(token, user, refreshToken = null) {
    localStorage.setItem('sh_token', token);
    localStorage.setItem('sh_user', JSON.stringify(user));
    if (refreshToken) localStorage.setItem('sh_refresh_token', refreshToken);
  },

  clearSession() {
    localStorage.removeItem('sh_token');
    localStorage.removeItem('sh_user');
    localStorage.removeItem('sh_refresh_token');
  },

  /**
   * Garde d'accès à appeler en tête des pages protégées.
   * Redirige vers login.html si non connecté, ou vers le bon
   * tableau de bord si le rôle ne correspond pas à la page.
   *   SmartHireAPI.requireAuth(['candidate'])
   */
  requireAuth(allowedRoles = null) {
    if (!this.isLoggedIn()) {
      window.location.href = 'login.html';
      return false;
    }
    const user = this.getUser();
    if (allowedRoles && !allowedRoles.includes(user.role)) {
      this.redirectByRole(user.role);
      return false;
    }
    return true;
  },

  logout() {
    // Révocation du refresh token côté serveur, en best-effort : on ne bloque
    // pas la déconnexion locale si l'API est injoignable.
    const refreshToken = this.getRefreshToken();
    if (refreshToken) {
      this.request('api/auth/logout', { method: 'POST', body: { refresh_token: refreshToken } }).catch(() => {});
    }
    this.clearSession();
    window.location.href = 'login.html';
  },

  /**
   * Échange le refresh token stocké contre un nouveau JWT d'accès.
   * Utile quand un appel API renvoie 401 (token expiré) : réessayer une fois
   * après refreshToken() avant de forcer une reconnexion complète.
   */
  async refreshToken() {
    const refreshToken = this.getRefreshToken();
    if (!refreshToken) throw new Error('Aucun refresh token disponible');

    const data = await this._rawRequest('api/auth/refresh', { method: 'POST', body: { refresh_token: refreshToken } });
    localStorage.setItem('sh_token', data.token);
    localStorage.setItem('sh_refresh_token', data.refresh_token);
    return data;
  },

  forgotPassword(email) {
    return this.request('api/auth/forgot-password', { method: 'POST', body: { email } });
  },

  resetPassword(payload) {
    return this.request('api/auth/reset-password', { method: 'POST', body: payload });
  },

  isLoggedIn() {
    return !!this.getToken();
  },

  /**
   * Requête brute sans gestion du refresh token (évite les boucles infinies).
   * Utilisé uniquement par refreshToken().
   */
  async _rawRequest(path, { method = 'GET', body = null } = {}) {
    const headers = { 'Content-Type': 'application/json' };
    const token = this.getToken();
    if (token) headers['Authorization'] = `Bearer ${token}`;

    let response;
    try {
      response = await fetch(`${API_BASE_URL}/${path}`, {
        method,
        headers,
        body: body ? JSON.stringify(body) : null,
      });
    } catch (networkError) {
      throw new Error("Impossible de contacter le serveur. Vérifie que l'API backend est démarrée.");
    }

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
      const message = data.error || `Erreur (${response.status})`;
      const fieldErrors = data.fields ? Object.values(data.fields).join(' ') : '';
      throw new Error(fieldErrors ? `${message} — ${fieldErrors}` : message);
    }

    return data;
  },

  /**
   * Appel générique vers l'API. Ajoute automatiquement le token JWT
   * si présent, et parse la réponse JSON.
   * 
   * Gestion automatique du refresh token :
   * - Si 401 (token expiré), tente un refresh automatique
   * - Si le refresh échoue aussi, redirige vers login.html
   */
  async request(path, { method = 'GET', body = null } = {}) {
    try {
      return await this._rawRequest(path, { method, body });
    } catch (err) {
      // Si erreur 401, tenter un refresh du token
      if (err.message && err.message.includes('Invalid or expired token')) {
        try {
          await this.refreshToken();
          // Réessayer la requête originale avec le nouveau token
          return await this._rawRequest(path, { method, body });
        } catch (refreshErr) {
          // Refresh échoué aussi → déconnecter
          this.clearSession();
          window.location.href = 'login.html';
          throw new Error('Session expirée. Veuillez vous reconnecter.');
        }
      }
      throw err;
    }
  },

  /**
   * Upload multipart (FormData) — distinct de request() car on ne doit
   * surtout pas fixer Content-Type nous-mêmes : le navigateur doit générer
   * le boundary multipart lui-même.
   */
  async uploadFile(path, fieldName, file) {
    const headers = {};
    const token = this.getToken();
    if (token) headers['Authorization'] = `Bearer ${token}`;

    const formData = new FormData();
    formData.append(fieldName, file);

    let response;
    try {
      response = await fetch(`${API_BASE_URL}/${path}`, {
        method: 'POST',
        headers,
        body: formData,
      });
    } catch (networkError) {
      throw new Error("Impossible de contacter le serveur. Vérifie que l'API backend est démarrée.");
    }

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
      const message = data.error || `Erreur (${response.status})`;
      throw new Error(message);
    }

    return data;
  },

  register(payload) {
    return this.request('api/auth/register', { method: 'POST', body: payload });
  },

  login(payload) {
    return this.request('api/auth/login', { method: 'POST', body: payload });
  },

  me() {
    return this.request('api/auth/me');
  },

  /** Redirige vers le bon tableau de bord selon le rôle renvoyé par l'API */
  redirectByRole(role) {
    const destinations = {
      candidate: 'candidate.html',
      recruiter: 'recruiter.html',
      admin: 'admin.html',
    };
    window.location.href = destinations[role] || 'index.html';
  },
};