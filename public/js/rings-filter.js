/*
 * Used only on /engagement-rings
 * Available params: minPrice, maxPrice, style, metal, engagementOnly, bridalOnly
 */

(function(){

var UrlParams = {
    get: function(){
        var i, urlVar, result = {},
            urlParts = location.search.substring(1).split('&');

        for (i in urlParts) {
            urlVar = urlParts[i].split('=');
            urlVar[0] && (result[ urlVar[0] ] = urlVar[1]);
        }
        return result;
    },
    set: function(newParams){
        var i, j, urlParts = [], params = this.get();
        for (i in newParams) {
            params[i] = newParams[i];
        }
        for (j in params) {
            //remove pamameter if 'all'
             params[j] !== 'all' && urlParts.push(j + '=' + params[j]);
        }
        urlParts.length
            //? history.replaceState('', document.title, location.pathname + '?' + urlParts.join('&'))
            ? (location.search = '?' + urlParts.join('&'))
            : this.delete();
    },
    delete: function(){
        //history.replaceState('', document.title, location.pathname);
        location.href = location.pathname;
    }
};

var PriceFilter = function(){};
$.extend(PriceFilter.prototype, {
    $range: $('.t-range-scale'),
    $min: $('#priceBlock .u-fl'),
    $max: $('#priceBlock .u-fr'),
    init: function(){
        var getVars = UrlParams.get();
        
        this.min = 0;
        this.max = 1600; //TODO: get real data
        
        this.currentMin = getVars.minPrice && +getVars.minPrice >= this.min
            ? +getVars.minPrice
            : this.getDefaultMin(this.max);
        
        this.currentMax = getVars.maxPrice && +getVars.maxPrice <= this.max
            ? +getVars.maxPrice
            : this.getDefaultMax(this.max);
        
        if (this.currentMin > this.currentMax) {
            this.currentMin = this.getDefaultMin();
            this.currentMax = this.getDefaultMax();
        }
        
        this.initSlider();
        this.initInputs();
    },
    initSlider: function(){
        var self = this;
        
        this.$slider = this.$range.slider({
            classes: {
                "ui-slider": 't-price-block',
                "ui-slider-range": 't-range-scale-active',
                "ui-slider-handle": 't-range-slider'
            },
            range: true,
            min: this.min,
            max: this.max,
            values: [this.currentMin, this.currentMax],
            slide: function(event, ui) {
                self.updateRange(ui.values[0], ui.values[1]);
            },
            stop: function(event, ui) {
                UrlParams.set({minPrice: ui.values[0], maxPrice: ui.values[1]});
            }
        });
        
        this.updateRange(
            this.$slider.slider('values', 0),
            this.$slider.slider('values', 1)
        );
    },
    initInputs: function(){
        this.$min.keyup(function(e){
            e.keyCode === 13 && UrlParams.set({
                minPrice: this.value.replace('$', '')
            });
        });
        this.$max.keyup(function(e){
            e.keyCode === 13 && UrlParams.set({
                maxPrice: this.value.replace('$', '')
            });
        });
    },
    updateRange: function(min, max){
        this.$min.val('$' + min);
        this.$max.val('$' + max);
    },
    getDefaultMin: function(){
        return Math.round(this.max / 9 * 1.5);
    },
    getDefaultMax: function(){
        return this.max - Math.round(this.max / 9);
    }
});

var RingsFilter = function(){};
$.extend(RingsFilter.prototype, {
    init: function(){
        (new PriceFilter()).init();
        this.initStyle();
        this.initMetal();
        this.initEngagementOnly();
        this.initBridalOnly();
        this.initShape();
        this.initShipping();
        this.initSort();
        
        this.initClear();
    },
    initStyle: function(){
        $('#ringStile li a').click(function(){
            var $li = $(this).parent();

            UrlParams.set({
                style: $li.hasClass('active') ? 'all' : $li.text().trim()
            });
            return false;
        });
    },
    initMetal: function(){
        $('#ringMetal li a').click(function(){
            var $li = $(this).parent();

            UrlParams.set({
                metal: $li.hasClass('active') ? 'all' : $li.text().trim().replace(/\s+/g, ' ')
            });
            return false;
        });
    },
    initClear: function(){
        $('#clear-filters').click(function(){
            UrlParams.delete();
            return false;
        });
    },
    initEngagementOnly: function(){
        $('#engagement_rings').change(function(){
            UrlParams.set({
                engagementOnly: this.checked ? true : 'all'
            });
        });
    },
    initBridalOnly: function(){
        $('#bridal_rings').change(function(){
            UrlParams.set({
                bridalOnly: this.checked ? true : 'all'
            });
        });
    },
    initShape: function(){
        $('#rings_diamond_shape').change(function(){
            UrlParams.set({shape: this.value});
        });
    },
    initShipping: function(){
        $('#rings_ship').change(function(){
            UrlParams.set({shipping: this.value});
        });
    },
    initSort: function(){
        $('#rings_sort_by').change(function(){
            UrlParams.set({sort: this.value});
        });
    }
});

(new RingsFilter()).init();

})();
