function XHR() {
    try {
        return new XMLHttpRequest();
    } catch (e) {}
    try {
        return new ActiveXObject('Msxml2.XMLHTTP.6.0');
    } catch (e) {}
    try {
        return new ActiveXObject('Msxml2.XMLHTTP.3.0');
    } catch (e) {}
    try {
        return new ActiveXObject('Msxml2.XMLHTTP');
    } catch (e) {}
    return false;
}

function submitURL()
{
    var xhr = new XHR();
    if (xhr == false)
        return true;
    var params = 'url='+document.getElementById('input-url').value+'&alias='+document.getElementById('input-alias').value;
    xhr.open('POST', 'api.php', true);
    xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    xhr.setRequestHeader('Content-length', params.length);
    xhr.setRequestHeader('Connection', 'close');
    xhr.onreadystatechange = function() {
	if(xhr.readyState == 4 && xhr.status == 200) {
            parseResponse(xhr.responseText);
	}
    }
    xhr.send(params);
    return false;
}

function parseResponse(response) {
    var data = response.substring(1);
    var result = document.getElementById('result');
    if (parseInt(response.charAt(0)) != 0) {
        result.innerHTML = '<div id="failure"><div>'+data+'</div></div>';
    } else {
        var alias = data.split('/');
        alias = alias[alias.length-1];
        result.innerHTML = '<div id="success"><div>Your URL was successfully shortened.</div><br/><input type="text" readonly="readonly" value="'+data+'"/> <a href="'+data+'" target="_blank"><img src="img/external.png" alt=""/></a> <a href="#" onclick="toggleQR(\''+alias+'\')">QR code</a><div id="qr"></div></div>';
    }
}

function toggleAlias() {
    var alias = document.getElementById('alias');
    var img = document.getElementById('switch-img');
    if (alias.style.display == 'block') {
        alias.style.display = 'none';
        img.src = 'img/alias-show.png';
    } else {
        alias.style.display = 'block';
        img.src = 'img/alias-hide.png';
    }
}

function toggleQR(alias) {
    var qr = document.getElementById('qr');
    if (qr.style.display == 'block') {
        qr.style.display = 'none';
    } else {
        if (qr.innerHTML == '')
            qr.innerHTML = '<img src="qr.php?alias='+alias+'" alt="QR code" />';
        qr.style.display = 'block';
    }
}
