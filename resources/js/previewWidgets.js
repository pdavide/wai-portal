export default (() => {
    const subTitle1 = document.getElementById("widget-subtitle-1");
    const subTitle2 = document.getElementById("widget-subtitle-2");
    const previewBlock = document.getElementById("widget-preview");
    const snippetBlock = document.getElementById("widget-code");
    const box = document.getElementById("widgets-preview-box");

    const widgetList = [
        ...document.querySelectorAll('div[data-type="widget-select"]')
    ];

    const init = () => {
        widgetList.map(widget => {
            widget.addEventListener("click", e => {
                const dataJson = e.currentTarget.firstElementChild.innerHTML;
                const idSite = e.currentTarget.firstElementChild.getAttribute(
                    "site"
                );
                const matomoUrl = box.getAttribute("data-url");
                const iframeUrl = generateWidgetUrl(
                    dataJson,
                    idSite,
                    matomoUrl
                );
                const frame = document.createElement("IFRAME");

                frame.setAttribute("width", "100%");
                frame.setAttribute("height", "350");
                frame.setAttribute("src", "");
                frame.src = decodeURIComponent(iframeUrl);
                frame.setAttribute("scrolling", "yes");
                frame.setAttribute("frameborder", "0");
                frame.setAttribute("marginheight", "0");
                frame.setAttribute("marginwidth", "0");

                previewBlock.innerHTML = "";
                previewBlock.appendChild(frame);

                snippetBlock.textContent = frame.outerHTML.replace(
                    /&amp;/g,
                    "&"
                );

                box.scrollIntoView({
                    behavior: "smooth",
                    block: "start",
                    inline: "nearest"
                });
            });
        });
    };

    const generateWidgetUrl = (data, idSite, baseUrl) => {
        let parseData = {};

        try {
            parseData = JSON.parse(data);
            subTitle1.innerHTML = parseData.name;
            subTitle2.innerHTML = parseData.name;
        } catch (error) {
            console.log("Error in parsing JSON", error);
        }

        let url = new URLSearchParams(baseUrl + "/index.php?module=Widgetize");

        url.append("action", "iframe");
        if (parseData.parameters?.containerId)
            url.append("containerId", parseData.parameters.containerId);
        url.append("disableLink", "0");
        url.append("widget", "1");
        url.append("moduleToWidgetize", parseData.module);
        url.append("actionToWidgetize", parseData.action);
        url.append("idSite", idSite);
        url.append("period", "day");
        url.append("date", "yesterday");
        url.append("disableLink", "1");

        return url;
    };

    return { init };
})();
