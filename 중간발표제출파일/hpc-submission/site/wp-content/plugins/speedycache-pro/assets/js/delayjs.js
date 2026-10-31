(function(){
let speedycache_js_events = ['mouseover','click','keydown','wheel','touchmove','touchstart'],
speedycache_djs_timeout = setTimeout(speedycache_delay_event, 10000);

speedycache_js_events.forEach((event) => {
	window.addEventListener(event, speedycache_delay_event, {passive: true});
});

async function speedycache_delay_event(){

	speedycache_js_events.forEach((event) => {
		window.removeEventListener(event, speedycache_delay_event, {passive: true});
	});

	if(speedycache_djs_timeout != null){
		clearTimeout(speedycache_djs_timeout);
		speedycache_djs_timeout = null;
	}

	let scripts = document.querySelectorAll('script[type="speedycache/javascript"]');

	// Start downloading every delayed file now, execution below stays one by one.
	scripts.forEach(js => {
		if(!js.hasAttribute('data-src')){
			return;
		}

		let preload = document.createElement('link');
		preload.rel = 'preload';
		preload.as = 'script';
		preload.href = js.getAttribute('data-src');
		document.head.appendChild(preload);
	});

	// DOMContentLoaded and load have already fired, so we catch the listeners the delayed scripts add and run them ourselves.
	let captured = {DOMContentLoaded: [], load: []},
	doc_add = document.addEventListener,
	win_add = window.addEventListener,
	win_onload = window.onload;

	function speedycache_capture(target, original){
		return function(type, listener, options){
			if(typeof listener === 'function' && (type === 'DOMContentLoaded' || (type === 'load' && target === window))){
				captured[type].push(listener.bind(target));
				return;
			}

			return original.call(this, type, listener, options);
		};
	}

	document.addEventListener = speedycache_capture(document, doc_add);
	window.addEventListener = speedycache_capture(window, win_add);

	// In sequence, so a script never runs before the one it depends on.
	for(let js of scripts){
		await new Promise(resolve => speedycache_load_js(js, resolve));
	}

	document.addEventListener = doc_add;
	window.addEventListener = win_add;

	// A delayed script might have assigned window.onload directly.
	if(window.onload && window.onload !== win_onload){
		captured.load.push(window.onload);
	}

	for(let type in captured){
		captured[type].forEach(listener => {
			try{
				listener(new Event(type));
			}catch(e){
				console.error(e);
			}
		});
	}
}

function speedycache_load_js(js, resolve){
	let async_js = document.createElement('script');

	let attr = js.getAttributeNames();
	attr.forEach(name => {
		if(name === 'type'){
			return;
		}

		async_js.setAttribute(name == 'data-src' ? 'src' : name, js.getAttribute(name));
	});

	async_js.setAttribute('type', 'text/javascript');

	if(js.hasAttribute('data-src')){
		// Error too, else one failed file blocks every script after it.
		async_js.addEventListener('load', resolve);
		async_js.addEventListener('error', resolve);
	} else {
		async_js.text = js.text;
	}

	// Chrome throws here for an inline script with a syntax error, it must not stop the scripts after it.
	try{
		js.after(async_js);
	}catch(e){
		console.error(e);
	}

	js.remove();

	// Inline scripts execute synchronously on insert.
	if(!js.hasAttribute('data-src')){
		resolve();
	}
}
})();
