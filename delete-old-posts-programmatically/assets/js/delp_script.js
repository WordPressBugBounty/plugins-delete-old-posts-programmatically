document.addEventListener("DOMContentLoaded", (event) => {
	const global_redirects_url_del 		= document.querySelector(".global-redirect-url-input-wrapper .after");
	const global_redirects_home_link 	= document.querySelector("#global_redirects_home_link");
	const global_redirects_input 			= document.querySelector("#global_redirect_url");
	
	global_redirects_home_link.addEventListener("click", (event) => {
		event.preventDefault();
		global_redirects_input.value = event.target.dataset.href
	});

	global_redirects_url_del.addEventListener('click', () => {
		global_redirects_input.value = '';
		global_redirects_url_del.style.display = 'none'; // hide 'x' on input hover
	});
});