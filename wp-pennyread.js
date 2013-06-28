/*
Plugin Name: PennyRead for WordPress
Plugin URI:  http://pennyread.com
Description: Bring PennyRead to your articles
Version:     1.0
Author:      Greg Deback, eko &co
Author URI:  http://eko.co
*/

(function() {

    tinymce.PluginManager.requireLangPack('pennyread');

    tinymce.create('tinymce.plugins.pennyread', {

	    init: function(ed, url) {
            ed.onNodeChange.add(function(ed, cm, node) {
                cm.setActive('pennyread', penny.active(node));
            });
	        ed.addButton('pennyread', {
		        title: 'pennyread.button',
		        image: url + '/icons/button.png',
		        onclick: function() {
		            ed.focus();
		            var sel  = ed.selection,
                        node = sel.getNode(),
                        box  = penny.active(node);
                    penny[box? 'clear': 'set'](ed, sel, box);
		        }
	        });
	    },
	    createControl : function(n, cm) {
	        return null;
	    },
	    getInfo : function() {
	        return {
		        longname:  "PennyRead for WordPress",
		        infourl:   "http://pennyread.com",
		        version:   "1.0",
		        author:    "Greg Deback, eko &co",
		        authorurl: "http://eko.co",
	        };
	    }
    });

    var penny = {

        words: 0,

        active: function(node) {
            var n = node,
                f = false;
            while (!f && n) {
                f = (n.tagName == 'DIV' &&
                     n.className.indexOf('pennyread') > -1)? n: false;
                n = n.parentNode;
            }
            return f;
        },

        set: function(ed, sel, node) {
		    var h = sel.getContent(),
                m = h? 'block': 'follow',
		        o = '<div class="pennyread">' +
                    '<span class="penny-read-more">' +
                    ed.translate('pennyread.' + m) + '</span>\n\n',
                c = '</div>';
            this.words = h.replace(/<\/?.+?>/g, '').split(' ').length;
            sel.setContent(o + h + c);
        },

        clear: function(ed, sel, node) {
            var h = node.innerHTML;
            h = h.replace(/<span class="penny\-?read\-more">.+?<\/span>\n*/i, '');
            this.words = 0;
            sel.select(node);
            sel.setContent(h);
        }

    };

    tinymce.PluginManager.add('pennyread', tinymce.plugins.pennyread);

})();
