/**
 * Class to manage compares via JS
 */

(() => {
  function Compares() {
    this.cookieName = 'compares';
    this.cookie = Cookies.getJSON(this.cookieName) || {};
  }

  Compares.prototype.add = function(group, id, onSuccess) {
    this.cookie[group] = this.cookie[group] ? Object.values(this.cookie[group]) : [];
    if (!this.cookie[group].find(itemId => itemId === id)) {
      this.cookie[group].push(id);
      this.persist();
      onSuccess();
    }
  };

  Compares.prototype.delete = function(group, id, onSuccess) {
    if (this.cookie[group]) {
      this.cookie[group] = this.cookie[group].filter(itemId => itemId !== id);
      this.persist();
      onSuccess();
    }
  };

  Compares.prototype.persist = function() {
    Cookies.set(this.cookieName, this.cookie);
  };

  window.Compares = Compares;
})();
