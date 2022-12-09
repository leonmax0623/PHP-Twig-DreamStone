/**
 * Class to manage composite via JS
 */

function Composite() {
  // this.availableGroups = ['products', 'diamonds', 'pendants'];
  this.cookieName = 'composite';
  this.cookie = Cookies.getJSON(this.cookieName) || {};
}

Composite.prototype.persist = function() {
  Cookies.set(this.cookieName, this.cookie);
};

Composite.prototype.add = function(group, _id, withAttributes) {
  this.cookie[group] = withAttributes && Object.keys(withAttributes).length ? { _id, withAttributes } : { _id };
  if (['products', 'pendants'].includes(group)) delete this.cookie[group === 'pendants' ? 'products' : 'pendants'];
  this.persist();
  return this;
};

Composite.prototype.delete = function(group) {
  delete this.cookie[group];
  this.persist();
  return this;
};

Composite.prototype.redirectTo = function(group) {
  if (group === 'pendants' && !this.cookie.pendants) {
    window.location.href = '/pendants?builder=1';
  } else if (group === 'products' && !this.cookie.products) {
    window.location.href = '/engagement-rings/search?builder=1';
  } else if (group === 'diamonds' && !this.cookie.diamonds) {
    window.location.href = '/loose-diamonds/search?builder=1';
  } else {
    const getVars = ['did=' + this.cookie.diamonds._id];
    if (group === 'products' && this.cookie.products) {
      getVars.push('pid=' + this.cookie.products._id);
      if (this.cookie.products.withAttributes && Object.keys(this.cookie.products.withAttributes).length) {
        const attr = [];
        Object.keys(this.cookie.products.withAttributes).forEach(key => {
          attr.push(encodeURIComponent(key + '=' + this.cookie.products.withAttributes[key]))
        });
        getVars.push('pwa=' + encodeURIComponent(attr.join('&')));
      }
    } else if (group === 'pendants' && this.cookie.pendants) getVars.push('nid=' + this.cookie.pendants._id);

    window.location.href = '/builder?' + getVars.join('&');
  }
};
