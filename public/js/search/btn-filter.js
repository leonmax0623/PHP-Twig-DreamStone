import {interfaceFilter} from "../lib/interface.js";
import {UrlParams,queryPromise} from "../lib/url.js";
import {diamondList} from "./diamondList.js";

export class abstractBtnFilter extends interfaceFilter{

    constructor(block, params, arg){
        super();
        if(this.isValidClass) {
            this.urlParams = new UrlParams(this.getUniKey());
            this.block = block;
            this.params = params;
            this.arg = arg;
        }else{
            console.log("Denied access is an abstract class");
        }
    }

    generateBlock(){
        let self = this;
        let action = this.params.find(x => x.action, true);
        this.params.forEach(function (block, key) {

            self.params[key] = Object.assign(self.params[key], {block_id: self.generateKey(block.code + block._id + self.getUniKey()) });
            $(self.block).append("<a id=\""+block.block_id+"\"  class=\"c-btn c-btn-sm "+( block.action || action === undefined ? "c-dark-btn" : "c-light-btn" )+"\">"+block.code+"</a>");
            action = true;
        });
        this.click();
    }

    click(){
        let self = this;
        let btns = $(this.block + ' a');
        for ( let btn of btns ) {
            btn.onclick = function(el) {
                self.params.forEach(function (param) {
                    let block = document.getElementById(param.block_id);
                    if( block.classList.contains("c-dark-btn") ){
                        block.classList.remove("c-dark-btn");
                        block.classList.add("c-light-btn");
                    }
                    if( !block.classList.contains("c-light-btn") ){
                        block.classList.add("c-light-btn");
                    }
                });
                this.classList.remove("c-light-btn");
                this.classList.add("c-dark-btn");
                self.getUrlParam(self.params[self.params.findIndex( i => i.block_id === this.id)].code);
            }
        }
    }

    generateKey(key){
        return window.btoa(key);
    }

    getUrlParam(key) {
        this.urlParams.set(key);
        let buildDiamond = queryPromise.Post('/search?'+this.urlParams.getAllUrl());
        buildDiamond.then(diamondList.buildImage,diamondList.error);
    }

    setParam(obj) {
        let getValue = this.isValidArray(this.urlParams.get());

        if (getValue.length) {
            let block = obj.params.findIndex(x => x.code === getValue);
            this.params.forEach(function (val, index) {
                obj.params[index] ={ code: val.code, _id: val._id};
                if(val.image){
                    obj.params[index] = Object.assign( obj.params[index] , { image: val.image});
                }
            });
            this.params[block] = Object.assign( this.params[block] , { action: true});
        }
    }

    isValidClass(){
        if( this.getUniKey() !== undefined){
            return true;
        }
        return false;
    }

    isValidArray(getParams){
        if(getParams!== undefined && getParams)
            return getParams;
        return [];
    }
}