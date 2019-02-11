(function($, undefined) {
	/** Note: $() will work as an alias for jQuery() inside of this function */

	function waitFor(condition, callback) {
		if (!condition()) {
			window.setTimeout(waitFor.bind(null, condition, callback), 100);
		}
		else {
			callback();
		}
	}

	var removeHash = function() {
	    history.pushState("", document.title, window.location.pathname+window.location.search);
	};

	function getBeforeHash(url) {
		var res = url.split('#')[0];
		if (res !== undefined) {
			return res;
		}
		return url;
	}
	function getAfterHash(url) {
		var res = url.split('#')[1];
		if (res !== undefined) {
			return res;
		}
		return false;
	}

	var singlepagepermalinks_init = function() {
		var options = {
			prefix: 'singlepagepermalinks',
			home_url: getBeforeHash(window.location.toString()),
			behavior_open: 'slideInFromBottom',
			behavior_close: 'slideOutToBottom',
			behavior_escape: true
		};
		if (typeof singlepagepermalinks === 'object') {
			$.extend(true, options, singlepagepermalinks);
		}
		options.classPost = options.prefix+'-post';
		options.classClose = options.prefix+'-close';

		// postHome
		if ($('.'+options.classPost)[0]) {
			postHome = $('.'+options.classPost+':eq(0)');
		}
		else {
			return;
		}

		var hash2post = {
			0: postHome.attr('id')
		};
		var currentHash = 0;

		// close
		function postClose() {
			if (currentHash === false) {
				return;
			}
			// close open post
			if (hash2post[currentHash] !== undefined) {
				$('#'+hash2post[currentHash]).removeClass(options.prefix+'-'+options.behavior_open).addClass(options.prefix+'-'+options.behavior_close).fadeOut('fast');
			}
			removeHash();
			currentHash = false;
			$('html,body').animate({scrollTop: 0},'slow');
		}
		// close - click
		$('body').on('click', '.'+options.classClose, function(e) {
			e.preventDefault();
			postClose();
			return false;
		});
		// close - escape
		if (options.behavior_escape) {
			$(document).keyup(function(e) {
				if (e.keyCode == 27) { // esc
					postClose();
				}
			});
		}

		// open
		function postOpen(hash) {
			if (hash !== 0) {
				hash = hash.replace(/[^A-Za-z0-9-]/,'').toLowerCase();
			}
			if (currentHash === hash) {
				return;
			}
			var openID = false;

			var postOpenEnd = function() {
				if (openID === false || openID === undefined) {
					return;
				}
				// close open post?
				if (currentHash !== false) {
					$('#'+hash2post[currentHash]).removeClass(options.prefix+'-'+options.behavior_open).addClass(options.prefix+'-'+options.behavior_close).fadeOut('fast', function() {
						$('#'+openID).fadeIn('fast').removeClass(options.prefix+'-'+options.behavior_close).addClass(options.prefix+'-'+options.behavior_open);
					});
				}
				else {
					$('#'+openID).fadeIn('fast').removeClass(options.prefix+'-'+options.behavior_close).addClass(options.prefix+'-'+options.behavior_open);
				}
				if (hash === 0) {
					removeHash();
				}
				else {
					window.location.hash = hash;
				}
				currentHash = hash;
				$('html,body').animate({scrollTop: 0},'slow');
			};

			// open existing
			if (hash2post[hash] !== undefined) {
				openID = hash2post[hash];
				postOpenEnd();
			}
			// open new
			else {
				var ajax_data = {
					action: options.prefix+'_get_post',
					post_name: hash
				};
				var ajaxDone = false;
		        $.post(options.ajaxurl, ajax_data, function(data) {
		        	// find class
		        	dataHTML = $('<span></span>').html(data).find('.'+options.classPost).first();
		        	// or find tag
		        	if (!dataHTML) {
						var tagName = postHome.prop("tagName").toLowerCase();
						dataHTML = $('<span></span>').html(data).find(tagName).first();
		        	}		        	
					if (dataHTML) {
						dataHTML.addClass(options.prefix+'-'+options.behavior_close).hide().appendTo(postHome.parent());
						openID = hash2post[hash] = dataHTML.attr('id');
					}
				},'html').always(function() {
    				ajaxDone = true;
  				});
	            waitFor(function(){return ajaxDone;}, postOpenEnd);
			}
		}
		// open - click
		$('body').on('click', 'a', function(e) {
			var href = $(this).attr('href');
			// home
			if (href == options.home_url) {
				e.preventDefault();
				postOpen(0);
				return false;
			}
			var hash = getAfterHash(href);
			if (hash) {
				var base = getBeforeHash(href);
				if (base == options.home_url) {
					e.preventDefault();
					postOpen(hash);
					return false;
				}
			}
		});

		// startup
		var hash = getAfterHash(window.location.href);
		if (hash) {
			postOpen(hash);
		}
	};

	$(document).ready(function() {
		singlepagepermalinks_init();
	});//document.ready
	
})(jQuery);