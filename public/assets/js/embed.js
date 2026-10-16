/**
 * IntelliSource Forms - Embeddable Widget
 *
 * Lightweight script for embedding forms on external websites.
 * Renders the form in an iframe (the full, session-bound enrollment form).
 *
 * Usage:
 * <div id="ff-form-123" data-ff-token="abc123"></div>
 * <script src="https://yoursite.com/wp-content/plugins/intellisource-forms/public/assets/js/embed.js" async></script>
 *
 * @package IntelliSourceForms
 * @since 2.1.0
 */

(function() {
    'use strict';

    // Configuration
    var FF_EMBED = {
        version: '2.1.0',
        initialized: false,
        instances: {},
        baseUrl: null,
        defaultOptions: {
            mode: 'iframe', // iframe only; the inline renderer was removed
            height: 'auto',
            minHeight: 400,
            maxHeight: 2000,
            theme: 'light',
            locale: 'en',
            onReady: null,
            onSubmit: null,
            onError: null,
            onStepChange: null
        }
    };

    /**
     * Initialize the embed system
     */
    function init() {
        if (FF_EMBED.initialized) return;
        FF_EMBED.initialized = true;

        // Detect base URL from script tag
        var scripts = document.getElementsByTagName('script');
        for (var i = 0; i < scripts.length; i++) {
            var src = scripts[i].src || '';
            if (src.indexOf('embed.js') !== -1) {
                FF_EMBED.baseUrl = src.replace(/\/public\/assets\/js\/embed\.js.*$/, '');
                break;
            }
        }

        // Find and initialize all embed containers
        var containers = document.querySelectorAll('[data-ff-token]');
        for (var j = 0; j < containers.length; j++) {
            initContainer(containers[j]);
        }

        // Listen for dynamic containers
        observeDOM();
    }

    /**
     * Initialize a single embed container
     */
    function initContainer(container) {
        var token = container.getAttribute('data-ff-token');
        if (!token || FF_EMBED.instances[token]) return;

        var options = parseOptions(container);
        var instance = {
            token: token,
            container: container,
            options: options,
            iframe: null,
            loaded: false
        };

        FF_EMBED.instances[token] = instance;

        // The inline renderer posted to /fffl/v1/embed/submit, which never
        // worked and has been removed. Always render the iframe.
        if (options.mode !== 'iframe' && window.console) {
            console.warn('FormFlow Lite embed: mode "' + options.mode + '" is not supported; rendering the iframe form.');
        }
        createIframe(instance);
    }

    /**
     * Parse options from data attributes
     */
    function parseOptions(container) {
        var options = Object.assign({}, FF_EMBED.defaultOptions);

        // Parse data attributes
        var attrs = container.attributes;
        for (var i = 0; i < attrs.length; i++) {
            var name = attrs[i].name;
            var value = attrs[i].value;

            if (name.indexOf('data-ff-') === 0) {
                var key = name.replace('data-ff-', '').replace(/-([a-z])/g, function(g) {
                    return g[1].toUpperCase();
                });
                if (key !== 'token') {
                    options[key] = value;
                }
            }
        }

        return options;
    }

    /**
     * Create iframe embed
     */
    function createIframe(instance) {
        var container = instance.container;
        var options = instance.options;

        // Create wrapper
        var wrapper = document.createElement('div');
        wrapper.className = 'ff-embed-wrapper';
        wrapper.style.cssText = 'position: relative; width: 100%; overflow: hidden;';

        // Create loading indicator
        var loader = document.createElement('div');
        loader.className = 'ff-embed-loader';
        loader.innerHTML = '<div class="ff-spinner"></div><span>Loading form...</span>';
        loader.style.cssText = 'display: flex; align-items: center; justify-content: center; padding: 40px; color: #666;';
        wrapper.appendChild(loader);

        // Create iframe
        var iframe = document.createElement('iframe');
        iframe.style.cssText = 'width: 100%; border: none; display: none; min-height: ' + options.minHeight + 'px;';
        iframe.setAttribute('allowfullscreen', 'true');
        iframe.setAttribute('loading', 'lazy');

        // Build iframe URL
        var iframeSrc = FF_EMBED.baseUrl + '/?ff_embed=1&token=' + encodeURIComponent(instance.token);
        if (options.locale) {
            iframeSrc += '&locale=' + encodeURIComponent(options.locale);
        }
        if (options.theme) {
            iframeSrc += '&theme=' + encodeURIComponent(options.theme);
        }

        iframe.src = iframeSrc;
        instance.iframe = iframe;

        // Handle load event
        iframe.onload = function() {
            instance.loaded = true;
            loader.style.display = 'none';
            iframe.style.display = 'block';

            if (typeof options.onReady === 'function') {
                options.onReady(instance);
            }
        };

        wrapper.appendChild(iframe);
        container.appendChild(wrapper);

        // Add styles
        addEmbedStyles();

        // Listen for messages from iframe
        window.addEventListener('message', function(event) {
            handleIframeMessage(event, instance);
        });
    }

    /**
     * Handle messages from iframe
     */
    function handleIframeMessage(event, instance) {
        var data = event.data;
        if (!data || typeof data !== 'object') return;

        switch (data.type) {
            case 'ff-resize':
                if (instance.iframe && data.height) {
                    var height = Math.min(
                        Math.max(data.height, instance.options.minHeight),
                        instance.options.maxHeight
                    );
                    instance.iframe.style.height = height + 'px';
                }
                break;

            case 'ff-submit':
                if (typeof instance.options.onSubmit === 'function') {
                    instance.options.onSubmit(data.data, instance);
                }
                break;

            case 'ff-error':
                if (typeof instance.options.onError === 'function') {
                    instance.options.onError(data.error, instance);
                }
                break;

            case 'ff-step':
                if (typeof instance.options.onStepChange === 'function') {
                    instance.options.onStepChange(data.step, instance);
                }
                break;
        }
    }

    /**
     * Add embed styles
     */
    function addEmbedStyles() {
        if (document.getElementById('ff-embed-styles')) return;

        var style = document.createElement('style');
        style.id = 'ff-embed-styles';
        style.textContent = [
            '.ff-embed-wrapper { background: #f9f9f9; border-radius: 8px; }',
            '.ff-embed-loader { display: flex; flex-direction: column; align-items: center; gap: 10px; }',
            '.ff-spinner { width: 30px; height: 30px; border: 3px solid #e0e0e0; border-top-color: #4F46E5; border-radius: 50%; animation: ff-spin 1s linear infinite; }',
            '@keyframes ff-spin { to { transform: rotate(360deg); } }',
            '.ff-embed-error { padding: 20px; text-align: center; color: #dc3545; }'
        ].join('\n');

        document.head.appendChild(style);
    }

    /**
     * Observe DOM for dynamically added containers
     */
    function observeDOM() {
        if (!window.MutationObserver) return;

        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) {
                        if (node.hasAttribute('data-ff-token')) {
                            initContainer(node);
                        }
                        var nested = node.querySelectorAll('[data-ff-token]');
                        nested.forEach(initContainer);
                    }
                });
            });
        });

        observer.observe(document.body, { childList: true, subtree: true });
    }

    /**
     * Public API
     */
    window.FFEmbed = {
        version: FF_EMBED.version,
        init: init,
        getInstance: function(token) {
            return FF_EMBED.instances[token] || null;
        },
        getInstances: function() {
            return Object.assign({}, FF_EMBED.instances);
        },
        destroy: function(token) {
            var instance = FF_EMBED.instances[token];
            if (instance) {
                instance.container.innerHTML = '';
                delete FF_EMBED.instances[token];
            }
        }
    };

    // Auto-initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
