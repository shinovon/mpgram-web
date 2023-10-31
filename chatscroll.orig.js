function getScrollY(){
	var a = window.pageXOffset !== undefined;
	var b = ((document.compatMode || "") === "CSS1Compat");
	return a ? window.pageYOffset : b ? document.documentElement.scrollTop : document.body.scrollTop;
}
function getHeight(){
	var a = window.innerHeight !== undefined;
	return a ? window.innerHeight : document.documentElement.clientHeight || document.body.clientHeight;
}
function autoScroll(force,dir){
	try{
		text=document.getElementById("text");
		bottom=document.getElementById("bottom");
		dir=dir?!reverse:reverse;
		if(force){
			if(dir)
				bottom.scrollIntoView();
			else
				text.scrollIntoView();
		}else{
			try{
				tw=text.clientHeight;
				sh=getHeight();
				sy=getScrollY();
				ph=document.body.scrollHeight;
				if(sy>ph-tw-sy){
					text.scrollIntoView();
				}
			}catch(e){
				text.scrollIntoView();
			}
		}
	}catch(e){}
}