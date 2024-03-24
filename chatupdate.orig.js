function rr(){
	if(typeof XMLHttpRequest==='undefined'){
		XMLHttpRequest=function(){
			try{
				return new ActiveXObject("Msxml2.XMLHTTP.6.0");
			}catch(e){}
			try{
				return new ActiveXObject("Msxml2.XMLHTTP.3.0");
			}catch(e){}
			try{
				return new ActiveXObject("Msxml2.XMLHTTP");
			}catch(e){}
			try{
				return new ActiveXObject("Microsoft.XMLHTTP");
			}catch(e){}
			return null;
		};
	}
	return new XMLHttpRequest();
}
function ee(e){
	if(e.message !== undefined && e.message !== null){
		alert(e.message);
	} else {alert(e);}
}
var r = null;
var o = "0";
function h() {
	if(r.readyState == 4){
		try{
			var e=r.responseText;
			if(e!=null&&e.length>1){
				var f=e.indexOf("||");
				if(f!=-1){
					if(longpoll){
						o=e.substring(0,f);
					}else{
						msg=e.substring(0,f);
					}
					e=e.substring(f+2);
					if(e.length>0){
						var msgs=document.getElementById("msgs");
						var d=document.createElement("div");
						d.innerHTML=e;
						for(var i=d.childNodes.length-1;i>=0;i--){
							e=d.childNodes[i];
							try {
								if(e instanceof HTMLBRElement) continue;
							}catch(x){}
							if(reverse){
								msgs.appendChild(e);
							}else{
								msgs.insertBefore(e,msgs.firstChild);
							}
						}
						while(msgs.childNodes.length>msglimit){
							msgs.removeChild(reverse ? msgs.firstChild : msgs.lastChild);
						}
					}
				}
				if(autoscroll && reverse){
					setTimeout("autoScroll(false,false)",500);
				}
			}
		}catch(e){ee(e);}
		setTimeout("a();",updint);
	}
}
var c=0;
function a(){
	c++;
	if(c>70)return;
	try{
		r=rr();
		if(r==null)return;
		r.onreadystatechange=h;
		r.open("GET",url+"&m="+msg+"&o="+o);
		r.send(null);
	}catch(e){ee(e);}
}
try{
	setTimeout("a()",updint);
}catch(e){ee(e);
}