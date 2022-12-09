export class  diamondList{

    static buildImage(result){
        let data = JSON.parse(result).res;
        let body = $(".o-col-lg-12.o-row .t-search-results");
        body.empty();
        $(".o-results-block .t-resalt-table").hide();
        $(".o-results-block .o-col-lg-12.o-row").show();

        data.forEach(function (diamondBlock, index) {
            body.append(
                "<li class=\"t-search-result-block t-search-block\" id=\""+diamondBlock._id+"\" >"+
                "    <div class=\"t-search-result-image-block\">"+
                "            <img src=\"images/example.jpg\" width=\"276\" height=\"126\""+
                "            class=\"reel\""+
                "            data-image=\"images/example.jpg\""+
                "            data-footage=\"6\""+
                "            data-frames=\"35\""+
                "            data-entry=\"1\">"+
                "            <label class=\"c-rotate-360-label\"></label>"+
                "    </div>"+
                "    <div class=\"t-search-result-description-block\">"+
                "        <span class=\"t-search-result-short-desc u-f16\">0.30 Carat</span>"+
                "        <span class=\"t-search-result-short-desc u-f16\">Round Brilliant Diamond</span>"+
                "        <span class=\"t-search-result-short-desc u-text-non-upper u-f18\">K Color, "+diamondBlock.clarity.code+" Clarity, Fair Cut,</span>"+
                "        <span class=\"t-search-result-price\">Price: $1.450</span>"+
                "        <a href=\"javascipt: void(0);\" onclick=\"$(this).toggleClass('active');\" class=\"t-table-view-icon t-table-view-icon-compare\"></a>"+
                "        <a href=\"javascipt: void(0);\" onclick=\"$(this).toggleClass('active');\" class=\"t-table-view-icon t-table-view-icon-wishlist\"></a>"+
                "    </div>" +
                "</li>"
            );
        });
    }

    static buildList(result){
        $(".o-results-block .o-col-lg-12.o-row").hide();
        $(".o-results-block .t-resalt-table").show();
        let data = JSON.parse(result).res;
        let body = $(".t-resalt-table .t-resalt-table-body");
        body.empty();

        data.forEach(function (diamondBlock, index) {
            body.append(
               "<ul class=\"t-table-list\">"+
                "    <li><img src=\"images/diamond-table.png\"></li>"+
                "    <li><span>Round</span></li>"+
                "    <li><span>0.7</span></li>"+
                "    <li><span>K</span></li>"+
                "    <li><span>SI2</span></li>"+
                "    <li><span>Excellent</span></li>"+
                "    <li><span>60.9</span></li>"+
                "    <li><span>60.5</span></li>"+
                "    <li><span>IGI</span></li>"+
                "    <li><span>$1.050</span></li>"+
                "    <li><span><a href=\"javascipt: void(0);\" onclick=\"$(this).toggleClass('active');\" class=\"t-table-view-icon t-table-view-icon-compare\"></a></span></li>"+
                "    <li><span><a href=\"javascipt: void(0);\" onclick=\"$(this).toggleClass('active');\" class=\"t-table-view-icon t-table-view-icon-wishlist\"></a></span></li>"+
                "    <li><span><a href=\"javascipt: void(0);\" onclick=\"$(this).toggleClass('active');\" class=\"t-table-view-icon t-table-view-icon-details\"></a></span></li>"+
                "</ul>"
            );
        });
    }

    static error(error){
        console.log("Error result Daimond");
    }
}