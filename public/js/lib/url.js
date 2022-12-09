export class UrlParams{
    constructor(param){
        this.param = param;
    };

    get(){
        let urlParts = new URLSearchParams((new URL(window.location)).search.slice(1));
        return urlParts.get(this.param);
    };

    set(params){
        let urlParts = new URLSearchParams((new URL(window.location)).search.slice(1));
        urlParts.set(this.param, params);
        history.pushState(null, '',  window.location.pathname +'?'+ urlParts.toString());
    };

    getAllUrl(){
        let urlParts = new URLSearchParams((new URL(window.location)).search.slice(1));
        return urlParts.toString();
    }

}

export class queryPromise{
    static Post(url, params = {}){
        return new Promise( function (resolve, reject) {
            var req = new XMLHttpRequest();

            req.open('POST', url);

            req.onload = function() {
                console.log(req.status);
                if (req.status == 200) {
                    resolve(req.response);
                } else {
                    reject(Error(req.statusText));
                }
            };

            // Handle network errors
            req.onerror = function() {
                reject(Error("Network Error"));
            };
            req.send(params);
        });
    }
}