$(window).scroll(function(){
  if ($(window).scrollTop() >= 60) {
    $(".c-header_main").addClass("c-header_main-fix");
    $(".t-totop-btn").addClass("show_btn");

  } else {
    $(".c-header_main").removeClass("c-header_main-fix");
    $(".t-totop-btn").removeClass("show_btn");
  }
});

function toggleBlock(button, block){
  $(button).toggleClass('open');
  $('#'+ block).toggleClass('show');
}
function CooseCurrency(active){
  const $el = $('#currency_value');
  if ($el.hasClass(active))
    return;

  $el.toggleClass(['t-top-icon-dollar', 't-top-icon-euro']);
  if (active === 't-top-icon-euro') {
    Cookies.set('currency', 'euro', 86400 * 30, '/');
  } else {
    Cookies.set('currency', '', -1, '/');
  }
  location.reload();
}
function showNav(nav){
  var main_nav_disp = $('#main_nav').css("display");
  var second_nav_disp = $('#second_nav').css("display");
  if ((nav == 'main_nav') && (second_nav_disp == 'block')){
    $('#second_nav').hide();
    $('#main_nav').show();
  }else if ((nav == 'main_nav') && (second_nav_disp == 'none')){
    $('#main_nav').toggle();
    $('body').toggleClass('s-sm-fixed');
  }else if((nav == 'second_nav') && (main_nav_disp == 'block')){
    $('#main_nav').hide();
    $('#second_nav').show();
  }else if ((nav == 'second_nav') && (main_nav_disp == 'none')){
    $('#second_nav').toggle();
    $('body').toggleClass('s-sm-fixed');
  }
}
function showImageBlock(el, image){
  if ($(window).width() > 768){
    const elem = document.createElement('img');
    elem.src = image;
    $(el).parents('.t-search-result-block')
      .addClass('t-show-img')
      .find('.t-show-image-block').append(elem);
  }
}
function hideImageBlock(el){
  if ($(window).width() > 768){
    $(el).parents('.t-search-result-block')
      .removeClass('t-show-img')
      .find('.t-show-image-block img').remove();
  }
}

function StonesToggle(name){
  if($('#'+ name).css('display') == 'block'){
    $('#'+ name).hide('slow')
  }else{
    $('.jewelry_stones').hide('slow');
    $('#'+ name).show('slow');
  }
}

$(document).ready(function() {
  $("#main_nav").on( "click", function(e) {
    var container = $("#main_nav").children( ".t-mob-nav-block" );
    // if the target of the click isn't the container nor a descendant of the container
    if (!container.is(e.target) && container.has(e.target).length === 0)
    {
      $("#main_nav").hide();
      $('body').removeClass('s-sm-fixed');
    }
  });
  $("#second_nav").on( "click", function(e) {
    var container = $("#second_nav").children( ".t-mob-nav-block" );
    // if the target of the click isn't the container nor a descendant of the container
    if (!container.is(e.target) && container.has(e.target).length === 0)
    {
      $("#second_nav").hide();
      $('body').removeClass('s-sm-fixed');
    }
  });
  /*-----filter hide/show------*/
  $("#t-filter-close-btn").on("click", function() {
    this.innerHTML = $("#t-filter-close-btn").hasClass('t-filter-open-btn') ? 'Close Filters' : 'Open Filters';
    $("#t-filter-close-btn").toggleClass('t-filter-open-btn');
    $(".c-filter-block").toggleClass('c-filter-block-hidden');
    return false;
  });
  /*-----/filter hide/show------*/
  /*-----color picker------*/
  $('.t-metal-open-btn').on("click", function(){
    $(this).next('div').children('ul').children('li').css({ display: "inline-block" });
    $(this).next('div').addClass('open');
  });
  $('.t-metal-color-radio-block').children('input[type=radio]').on("click", function(){
    var name = $(this).attr("name");
    $('input[type=radio]').each(function(){
      this_name = $(this).attr("name");
      this_check = $(this).prop('checked');
      if (this_name == name){
        if(!this_check){
          $(this).parent().css({ display: "none" });
        }
      }
    });
    $(this).parents('.t-metal-color-piker').removeClass('open');
  });
  /*----- /color picker------*/

  /*----------details block open/close search page ----------*/
  $(".t-table-view-icon-details").on( "click", function(e) {
    if ($(this).parents('.t-table-list').hasClass('active')){
      $(this).parents('.t-table-list').toggleClass('active');
      $(this).parents('.t-table-list').next('div.t-table-view-details-block').hide("slow");
    }else{
      $('.t-table-list').removeClass('active');
      $('.t-table-view-details-block').hide("slow");
      $(this).parents('.t-table-list').addClass('active');
      $(this).parents('.t-table-list').next('div.t-table-view-details-block').show("slow");
    }
  }).children('.t-table-view-icon').click(function(e) {
    return false;
  });

  /*----- /tooltip show/hide------*/
  $(".c-info-label").on( "click", function() {
    if($(this).parent().children('.t-tooltip').css('display') == 'none'){
      $('.t-tooltip').hide('slow');
    }
    $(this).parent().children('.t-tooltip').show('slow');
  });
  $(".c-close-btn").on( "click", function() {
    $(this).parents('.t-tooltip').hide('slow');
  });

  // JewelryStones
  var jwlrStoneChbxs = document.getElementsByName('jewelrystone');
  $("#all_stones").on('change', function() {
    for (var i = 0; i < jwlrStoneChbxs.length; i++) {
      jwlrStoneChbxs[i].checked = this.checked;
    }
    if (this.checked) {
      AddActiveClass('jewelrystone_btn');
      search('jewelrystone', 'all');
    } else {
      RemoveActiveClass('jewelrystone_btn');
      search('jewelrystone', '');
    }
  });
  $(".jewelrystone_checkbox").on('change', function() {
    var i, checkedChekboxes = [];
    for (i = 0; i < jwlrStoneChbxs.length; i++) {
      jwlrStoneChbxs[i].checked && checkedChekboxes.push(jwlrStoneChbxs[i].id);
    }
    if (checkedChekboxes.length) {
      AddActiveClass('jewelrystone_btn');
      if (checkedChekboxes.length < jwlrStoneChbxs.length) {
        document.getElementById('all_stones').checked = false;
        search('jewelrystone', checkedChekboxes.join(','));
      } else {
        document.getElementById('all_stones').checked = true;
        search('jewelrystone', 'all');
      }
    } else {
      document.getElementById('all_stones').checked = false;
      RemoveActiveClass('jewelrystone_btn');
      search('jewelrystone', '');
    }
  });

  // JewelryPearls
  var pearlChbxs = document.getElementsByName('pearl');
  $("#all_pearls").on('change', function() {
    for (var i = 0; i < pearlChbxs.length; i++) {
      pearlChbxs[i].checked = this.checked;
    }
    if (this.checked) {
      AddActiveClass('pearl_btn');
      search('jewelrypearl', 'all');
    } else {
      RemoveActiveClass('pearl_btn');
      search('jewelrypearl', '');
    }
  });
  $(".pearl_checkbox").on('change', function() {
    var i, checkedChekboxes = [];
    for (i = 0; i < pearlChbxs.length; i++) {
      pearlChbxs[i].checked && checkedChekboxes.push(pearlChbxs[i].id);
    }
    if (checkedChekboxes.length) {
      AddActiveClass('pearl_btn');
      if (checkedChekboxes.length < jwlrStoneChbxs.length) {
        document.getElementById("all_pearls").checked = false;
        search('jewelrypearl', checkedChekboxes.join(','));
      } else {
        document.getElementById("all_pearls").checked = true;
        search('jewelrypearl', 'all');
      }
    } else {
      document.getElementById("all_pearls").checked = false;
      RemoveActiveClass('pearl_btn');
      search('jewelrypearl', '');
    }
  });

  // BirthStones
  var brthStoneChbxs = document.getElementsByName('birthstone');
  $("#all_birthstone").on('change', function() {
    for(var i = 0; i < brthStoneChbxs.length; i++) {
      brthStoneChbxs[i].checked = this.checked;
    }
    if (this.checked) {
      AddActiveClass('birthstone_btn');
      search('birthstone', 'all');
    } else {
      RemoveActiveClass('birthstone_btn');
      search('birthstone', '');
    }
  });
  $(".birthstone_checkbox").on('change', function() {
    var i, checkedChekboxes = [];
    for(i = 0; i < brthStoneChbxs.length; i++) {
      if(brthStoneChbxs[i].checked){
        brthStoneChbxs[i].checked && checkedChekboxes.push(brthStoneChbxs[i].id);
      }
    }
    if (checkedChekboxes.length) {
      AddActiveClass('birthstone_btn');
      if (checkedChekboxes.length < jwlrStoneChbxs.length) {
        document.getElementById("all_birthstone").checked = false;
        search('birthstone', checkedChekboxes.join(','));
      } else {
        document.getElementById("all_birthstone").checked = true;
        search('birthstone', 'all');
      }
    } else {
      document.getElementById("all_birthstone").checked = false;
      RemoveActiveClass('birthstone_btn');
      search('birthstone', '');
    }
  });

  $('#loginForm').validate({
    rules: {
      email: {
        email: true,
        required: true
      },
      password: {
        required: true,
        minlength: 6
      },
    }
  });
  $('#change-login').validate({
    rules: {
      email: {
        email: true,
        required: true
      },
      password: {
        required: true,
        minlength: 6
      },
      first_name: {
        required: true,
        minlength: 2,
        maxlength: 15
      },
      last_name: {
        required: true,
        minlength: 2,
        maxlength: 40
      },
    }
  });
  $('#registration').validate({
    rules: {
      email: {
        email: true,
        required: true
      },
      first_name: {
        required: true,
        minlength: 2,
        maxlength: 15
      },
      last_name: {
        required: true,
        minlength: 2,
        maxlength: 40
      },
      password: {
        required: true,
        minlength: 6
      },
      password2: {
        required: true,
        equalTo: "#registerPassword"
      },
    }
  });
  $('#forgotForm').validate({
    rules: {
      userEmail: {
        email: true,
        required: true
      },
    }
  });
  $('#billing-shipping').validate({
    rules: {
      shipping_company: {
        minlength: 2,
        maxlength: 40
      },
      shipping_phone_extension: {
        minlength: 6,
        maxlength: 20
      },
      billing_company: {
        minlength: 2,
        maxlength: 40
      },
      billing_phone_extension: {
        minlength: 6,
        maxlength: 20
      },
    }
  });
});


function AddActiveClass(btn){
  $("#" + btn).removeClass('c-light-btn');
  $("#" + btn).addClass('c-dark-btn');
}
function RemoveActiveClass(btn){
  $("#" + btn).removeClass('c-dark-btn');
  $("#" + btn).addClass('c-light-btn');
}

window.UrlParams = {
  get: function () {
    var i, urlVar, result = {},
      urlParts = location.search.substring(1).split('&');

    for (i in urlParts) {
      urlVar = urlParts[i].split('=');
      urlVar[0] && (result[decodeURIComponent(urlVar[0])] = decodeURIComponent(urlVar[1]));
    }
    return result;
  },
  set: function (newParams, fullReload = true) {
    var i, j, urlParts = [], params = this.get();
    for (i in newParams) {
      params[i] = newParams[i];
    }
    for (j in params) {
      params[j] && urlParts.push(encodeURIComponent(j) + '=' + encodeURIComponent(params[j]));
    }
    if (urlParts.length) {
      fullReload
        ? (location.search = '?' + urlParts.join('&'))
        : (history.replaceState('', document.title, location.pathname + '?' + urlParts.join('&')));
    } else {
      this.delete(fullReload);
    }
  },
  delete: function (fullReload = true) {
    fullReload
      ? location.search = ''
      : history.replaceState('', document.title, location.pathname);

  }
};

function isPlainObjectsEqual(obj1, obj2) {
  const obj1keys = Object.keys(obj1);
  const obj2keys = Object.keys(obj2);
  if (obj1keys.length !== obj2keys.length)
    return false;

  let isEqual = true;
  obj1keys.forEach(key => {
    if (obj1[key] !== obj2[key])
      isEqual = false;
  });
  return isEqual;
}

function loadPageData(url, itemsWrapper, replace = false, callback = null) {
  $.ajax({
    url: `${url}${url.includes('?') ? '&' : '?'}json`,
    dataType: 'json',
    success: function (r) {
      if (replace) {
        window.lastOffset = 0;
        window.offset = window.limit ? +window.limit : 0; // hot fix
        window.finish = false;
        $(itemsWrapper).html(r.items ? r.items.join("\n") : '');
      } else {
        $(itemsWrapper).append(r.items ? r.items.join("\n") : '');
      }
      if (callback) callback(r.finish, r.total);
    }
  });
}
