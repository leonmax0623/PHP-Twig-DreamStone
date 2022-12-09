import  {abstractBtnFilter}   from "./btn-filter.js";
import  {sliderRangeArray, sliderRange}  from './slider-filter.js';
import {UrlParams, queryPromise} from "../lib/url.js";
import {diamondList} from "./diamondList.js";

class filterColor extends sliderRangeArray {

    constructor(block) {
        let params = document.getElementById("colors-template").innerHTML;
        if(params){
            params = JSON.parse(params.replace(/&quot;/g,'"'));
        }
        else {
            console.log("Error params");
        }
        super(block, params);
        this.buildRules();
        this.minScale = 0;
        this.setSliderRange(this.minScale, params.length);
    }

    getUniKey() {
        return "filterColor"
    }

    start() {
        this.setParams(this);
        this.setExtraOptions();
        super.start();
    }

    setExtraOptions(){
        let self = this;
        this.$slideObject = Object.assign(this.$slideObject, {stop: function (event, ui) {
                self.getParams(ui.values[0], ui.values[1]);
            }});
    }
}

class filterPrice extends sliderRange {

    constructor(block) {
        let scale = document.getElementById("price-template").innerHTML;
        if(!scale)
            scale ={min: 0, max: 1600};
        else {
            scale = JSON.parse((scale.replace(/&quot;/g,'"')));
        }
        super(block, scale.max);
        this.minScale = scale.min;
        this.maxScale = scale.max;
        this.setSliderRange(this.minScale, this.maxScale);
    }

    getUniKey() {
        return "filterPrice";
    }

    start() {
        this.setParams(this);
        this.setExtraOptions();
        super.start();
        this.setInputVal(this.$slider.slider("values", 0), this.$slider.slider("values", 1));
    }

    setInputVal(min, max){
        $(this.block + ' .t-price-input.u-fl').val(min);
        $(this.block + ' .t-price-input.u-fr').val(max);
    }

    setExtraOptions(){
        let self = this;
        this.$slideObject = Object.assign(this.$slideObject, {
            stop: function (event, ui) {
                self.setInputVal(ui.values[0], ui.values[1]);
                self.getParams(ui.values[0], ui.values[1]);
            }});
    }
}

class filterClarity extends sliderRangeArray {

    constructor(block) {
        let params = document.getElementById("clarity-template").innerHTML;
        if(params){
            params = JSON.parse(params.replace(/&quot;/g,'"'));
        }
        else {
            console.log("Error params");
        }
        super(block, params);
        this.buildRules();
        this.minScale = 0;
        this.setSliderRange(this.minScale, params.length);
    }

    getUniKey() {
        return "filterClarity"
    }

    start() {
        this.setParams(this);
        this.setExtraOptions();
        super.start();
    }

    setExtraOptions(){
        let self = this;
        this.$slideObject = Object.assign(this.$slideObject, {stop: function (event, ui) {
                self.getParams(ui.values[0], ui.values[1]);
            }});
    }
}

class filterCut extends sliderRangeArray {

    constructor(block) {
        let params = document.getElementById("cuts-template").innerHTML;
        if(params){
            params = JSON.parse(params.replace(/&quot;/g,'"'));
        }
        else {
            console.log("Error params");
        }
        super(block, params);
        this.buildRules();
        this.minScale = 0;
        this.setSliderRange(this.minScale, params.length);
    }

    getUniKey() {
        return "filterCut"
    }

    start() {
        this.setParams(this);
        this.setExtraOptions();
        super.start();
    }

    setExtraOptions(){
        let self = this;
        this.$slideObject = Object.assign(this.$slideObject, {stop: function (event, ui) {
                self.getParams(ui.values[0], ui.values[1]);
            }});
    }
}

class filterFloat extends sliderRange{

    getFormat(value){
        return value/100;
    }

    setFormat(value){
        return value * 100;
    }

    setParams(obj) {
        let getPrice = this.isValidArray(this.urlParams.get());
        if (getPrice.length) {
            this.setSliderLocation(this.setFormat(getPrice[0]), this.setFormat(getPrice[1]));
        } else {
            this.setSliderLocation(this.minScale, this.maxScale);
        }
    }

    setExtraOptions(){
        let self = this;
        this.$slideObject = Object.assign(this.$slideObject, {
            stop: function (event, ui) {
                self.setInputVal(ui.values[0], ui.values[1]);
                self.getParams(self.getFormat(ui.values[0]), self.getFormat(ui.values[1]));
            }});
    }

    start() {
        this.setParams(this);
        this.setExtraOptions();
        super.start();
        this.setInputVal(this.$slider.slider("values", 0), this.$slider.slider("values", 1));

    }

    setInputVal(min, max){
        $(this.block + ' .t-price-input.u-fl').val(this.getFormat(min));
        $(this.block + ' .t-price-input.u-fr').val(this.getFormat(max));
    }
}

class filterCarat extends filterFloat {

    constructor(block) {
        let scale = document.getElementById("carat-template").innerHTML;
        if(!scale)
            scale ={min: 0.3, max: 30};
        else {
            scale = JSON.parse((scale.replace(/&quot;/g,'"')));
        }
        super(block, scale);
        this.minScale = this.setFormat(scale.min);
        this.maxScale = this.setFormat(scale.max);
        this.setSliderRange(this.minScale, this.maxScale);
    }

    getUniKey() {
        return "filterCarat";
    }
}

class filterDepth extends filterFloat {

    constructor(block) {
        let maxScale = 10000;
        super(block, maxScale);
        this.minScale = 0;
        this.maxScale = maxScale;
        this.setSliderRange(this.minScale, this.maxScale);
    }

    getUniKey() {
        return "filterDepth";
    }
}

class filterTable extends filterDepth {

    constructor(block) {
        super(block);
    }

    getUniKey() {
        return "filterTable";
    }
}

class filterRatio extends filterFloat {

    constructor(block) {
        let scale = document.getElementById("lengthToWidthRatio-template").innerHTML;
        if(!scale)
            scale ={min: 0, max: 5};
        else {
            scale = JSON.parse((scale.replace(/&quot;/g,'"')));
        }
        super(block, scale);
        this.minScale = this.setFormat(scale.min);
        this.maxScale = this.setFormat(scale.max);
        this.setSliderRange(this.minScale, this.maxScale);
    }

    getUniKey() {
        return "filterRatio";
    }
}

let fColor = new filterColor("#colorBlock");
fColor.start();

let fPrice = new filterPrice("#priceBlock");
fPrice.start();

let fClarity = new filterClarity("#clarityBlock");
fClarity.start();

let fCut = new filterCut("#cutBlock");
fCut.start();

let fCarat = new filterCarat("#caratBlock");
fCarat.start();

let fDepth = new filterDepth("#depthBlock");
fDepth.start();

let fTable = new filterTable("#tableBlock");
fTable.start();

let fRatio = new filterRatio("#lengthToWidthRatioBlock");
fRatio.start();

class btnFluorescence extends abstractBtnFilter{
    constructor(block, arg){

        let params = document.getElementById("flourence-template").innerHTML;
        if(!params)
           console.log("Error params");
        else {
            params = JSON.parse((params.replace(/&quot;/g,'"')));
        }

        super(block, params, arg);
        this.setParam(this);
        this.generateBlock();

    }

    getUniKey(){
        return "btnFluorescence";
    }
}

class btnOrigin extends abstractBtnFilter{
    constructor(block, arg){
        let params = document.getElementById("origin-template").innerHTML;
        if(!params)
            console.log("Error params");
        else {
            params = JSON.parse((params.replace(/&quot;/g,'"')));
        }
        super(block, params, arg);
        this.setParam(this);
        this.generateBlock();

    }

    getUniKey(){
        return "btnOrigin";
    }
}

class btnReport extends abstractBtnFilter{
    constructor(block, arg){
        let params = document.getElementById("report-template").innerHTML;
        if(!params)
            console.log("Error params");
        else {
            params = JSON.parse((params.replace(/&quot;/g,'"')));
        }
        super(block, params, arg);
        this.setParam(this);
        this.generateBlock();

    }

    getUniKey(){
        return "btnReport";
    }
}

class btnPolish extends abstractBtnFilter{
    constructor(block, arg){
        let params = document.getElementById("polish-template").innerHTML;
        if(!params)
            console.log("Error params");
        else {
            params = JSON.parse((params.replace(/&quot;/g,'"')));
        }
        super(block, params, arg);
        this.setParam(this);
        this.generateBlock();

    }

    getUniKey(){
        return "btnPolish";
    }
}

class btnSymmetry extends abstractBtnFilter{
    constructor(block, arg){
        let params = document.getElementById("symmetry-template").innerHTML;
        if(!params)
            console.log("Error params");
        else {
            params = JSON.parse((params.replace(/&quot;/g,'"')));
        }
        super(block, params, arg);
        this.setParam(this);
        this.generateBlock();

    }

    getUniKey(){
        return "btnSymmetry";
    }
}


class btnShape extends abstractBtnFilter{
    constructor(block, arg){
        let params = document.getElementById("shape-template").innerHTML;
        if(!params)
            console.log("Error params");
        else {
            params = JSON.parse((params.replace(/&quot;/g,'"')));
        }
        super(block, params, arg);
        this.setParam(this);
        this.generateBlock();

    }
    generateBlock(){
        let self = this;
        let action = this.params.find(x => x.action, true);
        this.params.forEach(function (block, key) {
            self.params[key] = Object.assign(self.params[key], {block_id: self.generateKey(block.code + block._id + self.getUniKey())});
            $(self.block).append("<li  class=\"" + (block.action || action === undefined ? "active" : "") + "\" id=\"" + self.params[key].block_id + "\">" + rhtmlspecialchars(block.image) + block.code + "</li>");
            action = true;
        });
        this.click();
    }

    click(){
        let self = this;
        let btns = $(this.block + ' li');
        for ( let btn of btns ) {
            btn.onclick = function(el) {
                self.params.forEach(function (param) {
                    let block = document.getElementById(param.block_id);
                    if( block.classList.contains("active") ){
                        block.classList.remove("active");
                    }
                });
                this.classList.add("active");
                self.getUrlParam(self.params[self.params.findIndex( i => i.block_id === this.id)].code);            }
        }

    }

    getUniKey(){
        return "btnShape";
    }
}
function rhtmlspecialchars(str) {
    if (typeof(str) == "string") {
        str = str.replace(/&gt;/ig, ">");
        str = str.replace(/&lt;/ig, "<");
        str = str.replace(/&#039;/g, "'");
        str = str.replace(/&quot;/ig, '"');
        str = str.replace(/&#x3d;/ig, '=');

        str = str.replace(/&amp;/ig, '&'); /* must do &amp; last */
    }
    return str;
};

let btnShape_ = new btnShape("#shapeFilter");

let btnF = new btnFluorescence("#fluorescenceFilter");

let btnO = new btnOrigin("#originFilter");

let btnR = new btnReport("#reportFilter");

let btnP = new btnPolish("#polishFilter");

let btnS = new btnSymmetry("#symmetryFilter");


//Type display diamond

class typeDiamond{
    static getList(){
        let param = new UrlParams('dispayType');
        let buildDiamond = queryPromise.Post('/search?'+param.getAllUrl());
        buildDiamond.then(diamondList.buildList,diamondList.error);
       let el = $('header.t-results-header .u-fl.s-md-invisible a.t-display-style-list');
       for (let i of el){
           i.classList.add("active");
       }
    }

    static getBlock(){
        let param = new UrlParams('dispayType');
        let buildDiamond = queryPromise.Post('/search?'+param.getAllUrl());
        buildDiamond.then(diamondList.buildImage,diamondList.error);
        let el = $('header.t-results-header .u-fl.s-md-invisible a.t-display-style-block');
        for (let i of el){
            i.classList.add("active");
        }
    }

    static getBlockFront(){
        let param = new UrlParams('dispayType');
        let buildDiamond = queryPromise.Post('/search?'+param.getAllUrl());
        buildDiamond.then(diamondList.buildImage,diamondList.error);
        let el = $('header.t-results-header .u-fl.s-md-invisible a.t-display-style-block-front');
        for (let i of el){
            i.classList.add("active");
        }
    }
};

class displayType{

    static getAction(){
        let param = (new UrlParams('dispayType')).get();
        if( param){
            typeDiamond[param]();
        }else {
            typeDiamond.getList();
        }

    }
    static click(){
        let btns, els;
        btns = els  = $('header.t-results-header .u-fl.s-md-invisible a');
        let param = new UrlParams('dispayType');
        for(let btn of btns){
            btn.onclick = function () {
                for(let el of els){
                    el.classList.remove("active");
                }
                if(btn.classList.contains("t-display-style-list")){
                    param.set("getList");
                    typeDiamond.getList();

                }
                if(btn.classList.contains("t-display-style-block")){
                    param.set("getBlock");
                    typeDiamond.getBlock();
                }
                if(btn.classList.contains("t-display-style-block-front")){
                    typeDiamond.getBlockFront();
                    param.set("getBlockFront");
                }
                btn.classList.add("active");
            }
        }
    }

    static run(){
        displayType.getAction();
        displayType.click()
    }
}

displayType.run();