import {interfaceFilter} from "../lib/interface.js";
import {UrlParams, queryPromise} from "../lib/url.js";
import {diamondList} from "./diamondList.js";

class abstractSliderFilter extends interfaceFilter{

    constructor(block, params){
        super();
        if(this.isValidClass()){
            this.urlParams = new UrlParams(this.getUniKey());
            this.block = block;
            this.params = params;
            this.$slideObject = this.setSliderDefault();
        }else{
            console.log("Denied access is an abstract class");
        }

    }

    buildRules(){
        let self = this;
        this.params.forEach(function(block, index ){
            let li =  document.createElement('li');
            li.innerHTML = '<span>'+block.code+'</span>';
            self.params[index] = block.code;
            $(self.block+' .t-range-data').append(li);
        });
    }

    setSliderDefault(){
        return {
            classes: {
                "ui-slider": 't-price-block',
                "ui-slider-range": 't-range-scale-active',
                "ui-slider-handle": 't-range-slider'
            }
        }
    }

    setSliders(){
        this.$slideObject = Object.assign(this.$slideObject, { range: true } );
    }

    setSliderRange(min, max){
        this.$slideObject = Object.assign(this.$slideObject, {min: min, max: max });
    }

    setSliderLocation(min, max){
        if(max === undefined)
            this.$slideObject = Object.assign(this.$slideObject, {value: min });
        else {
            this.setSliders();
            this.$slideObject = Object.assign(this.$slideObject, {values: [min, max]});
        }
    }

    start(){
        this.$slider =  $(this.block + ' .t-range-scale').slider(this.$slideObject);
    }

    isValidArray(getParams){
        if(getParams!== undefined && getParams)
            return getParams.split(',');
        return [];
    }

    isValidClass(){
        if( this.getUniKey() !== undefined ){
            return true;
        }
        return false;
    }
}

export class sliderRangeArray extends abstractSliderFilter{

    getParams(minKey, maxKey) {
        let params = [];
        this.params.forEach(function (res, key) {
            if (key >= minKey && key < maxKey) {
                params.push(res);
            }
        });
        this.urlParams.set(params);
        let buildDiamond = queryPromise.Post('/search?'+this.urlParams.getAllUrl());
        buildDiamond.then(diamondList.buildImage,diamondList.error);
    }

    setParams(obj) {
        let getColor = this.isValidArray(this.urlParams.get());
        if (getColor.length) {
            let min = this.params.findIndex( i => i === getColor[0]);
            this.setSliderLocation(min, min + getColor.length);
        }else{
            this.setSliderLocation(this.minScale, this.params.length);
        }
    }
}

export class sliderRange extends abstractSliderFilter{


    setParams(obj) {
        let getPrice = this.isValidArray(this.urlParams.get());
        if (getPrice.length) {
            this.setSliderLocation(getPrice[0],getPrice[1]);
        }else{
            this.setSliderLocation(this.minScale, this.maxScale);
        }
    }

    getParams(min, max) {
        let params = [min, max];
        this.urlParams.set(params);
        let buildDiamond = queryPromise.Post('/search?'+this.urlParams.getAllUrl());
        buildDiamond.then(diamondList.buildImage,diamondList.error);
    }
}
