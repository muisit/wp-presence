const controllers  = {};

export function abort_all_calls(type) {
    console.log("aborting all fetch calls for "+ type);
    if(controllers[type]) {
        controllers[type].abort();
        delete controllers[type];
    }
}

function validateResponse() {
    return res => {
        return res.json().then(json => {
            console.log('validate response ',json);
            if (!json || !json.success) {
                console.log('no success entry found or success is false');
                const error = new Error(res.statusText);
                error.response = json;
                throw error;
            }
            return json;
        })
    };
}

function validFetch(cnt, path, pdata, options, headers = {}) {
    if(!controllers[cnt]) {
        controllers[cnt]=new AbortController();
    }
    const contentHeaders = Object.assign({
        "Accept": "application/json",
        "Content-Type": "application/json"} , headers);

    const data = {
        path: path,
        nonce: wppresence.nonce, 
        model: pdata
    };

    const fetchOptions = Object.assign({}, {headers: contentHeaders}, options, {
        credentials: "same-origin",
        redirect: "manual",
        method: 'POST',
        signal: controllers[cnt].signal,
        body: JSON.stringify(data)
    });

    console.log('calling fetch using '+JSON.stringify(data));
    return fetch(wppresence.url, fetchOptions)
        .then(validateResponse())
        .catch(err => {
            if(err.name === "AbortError") {
                console.log('disregarding aborted call');
            }
            else {
                console.log("error in fetch: ",err);
                throw err;
            }
        });
}

function fetchJson(cnt,path, data={}, options = {}, headers = {}) {
    //console.log('valid fetch using data '+JSON.stringify(data));
    return validFetch(cnt,path, data, options, headers);
}

export function api_list(section,path, settings) {
    return fetchJson(section,path,settings);
}

export function api_misc(section, path, action, fields) {
    return fetchJson(section, path + "/" + action,fields);
}
