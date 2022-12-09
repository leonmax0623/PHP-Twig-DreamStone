/**
 * Class to manage favorites via JS
 */

(() => {
  const prepareItem = (group, item) => group === 'composites' ? {
    product: {
      _id: item.product._id,
      withAttributes: item.product.withAttributes || {},
    },
    diamond: {
      _id: item.diamond._id,
      withAttributes: item.diamond.withAttributes || {},
    },
  } : {
    _id: item._id,
    withAttributes: item.withAttributes || {},
  };

  const isSame = (group, item1, item2) => group === 'composites' ? (
    item1.product._id === item2.product._id
    && isPlainObjectsEqual(item1.product.withAttributes, item2.product.withAttributes)
    && item1.diamond._id === item2.diamond._id
    && isPlainObjectsEqual(item1.diamond.withAttributes, item2.diamond.withAttributes)
  ) : (
    item1._id === item2._id
    && isPlainObjectsEqual(item1.withAttributes, item2.withAttributes)
  );

  function Favorites(isLogged) {
    this.isLogged = isLogged;
    this.cookieName = 'favorites';
    this.cookie = Cookies.getJSON(this.cookieName) || {};
  }

  Favorites.prototype.add = function(group, newItem, onSuccess) {
    const item = prepareItem(group, newItem);
    if (this.isLogged) {
      $.post('/user/favorites', { group, item }, onSuccess);
    } else {
      this.cookie[group] = this.cookie[group] || [];
      if (!this.cookie[group].find(favorite => isSame(group, favorite, item))) {
        this.cookie[group].push(item);
        this.persist();
        onSuccess();
      }
    }
  };

  Favorites.prototype.delete = function(group, newItem, onSuccess) {
    const item = prepareItem(group, newItem);
    if (this.isLogged) {
      $.ajax({ type: 'DELETE', url: '/user/favorites', data: { group, item }, success: onSuccess });
    } else if (this.cookie[group]) {
      this.cookie[group] = this.cookie[group].filter(favorite => !isSame(group, favorite, item));
      this.persist();
      onSuccess();
    }
  };

  Favorites.prototype.persist = function() {
    Cookies.set(this.cookieName, this.cookie);
  };

  window.Favorites = Favorites;
})();
