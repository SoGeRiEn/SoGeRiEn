(function () {
    function cleanupInjectedNodes() {
        var lockScreens = document.querySelectorAll('veepn-lock-screen');
        for (var i = 0; i < lockScreens.length; i += 1) {
            lockScreens[i].remove();
        }

        var backdrops = document.querySelectorAll('.modal-backdrop');
        for (var j = 0; j < backdrops.length; j += 1) {
            backdrops[j].remove();
        }

        if (document.body) {
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('padding-right');
        }
    }

    function startCleanupObserver() {
        cleanupInjectedNodes();
        var cleanupScheduled = false;
        var cleanupTimer = 0;

        function scheduleCleanup(delayMs) {
            if (cleanupScheduled) {
                return;
            }
            cleanupScheduled = true;
            cleanupTimer = window.setTimeout(function () {
                cleanupScheduled = false;
                cleanupTimer = 0;
                cleanupInjectedNodes();
            }, delayMs);
        }

        function isRelevantMutation(mutations) {
            for (var i = 0; i < mutations.length; i += 1) {
                var mutation = mutations[i];
                if (!mutation || mutation.type !== 'childList') {
                    continue;
                }

                if (mutation.addedNodes && mutation.addedNodes.length) {
                    for (var j = 0; j < mutation.addedNodes.length; j += 1) {
                        var node = mutation.addedNodes[j];
                        if (!node || node.nodeType !== 1) {
                            continue;
                        }

                        var tag = (node.tagName || '').toLowerCase();
                        var cls = node.classList;
                        if (
                            tag === 'veepn-lock-screen' ||
                            (cls && cls.contains('modal-backdrop')) ||
                            (node.matches && node.matches('.modal-backdrop, .modal.show, [aria-modal="true"], veepn-lock-screen')) ||
                            (node.querySelector && node.querySelector('.modal-backdrop, .modal.show, [aria-modal="true"], veepn-lock-screen'))
                        ) {
                            return true;
                        }
                    }
                }
            }

            return false;
        }

        var observer = new MutationObserver(function (mutations) {
            if (isRelevantMutation(mutations || [])) {
                scheduleCleanup(50);
            }
        });
        observer.observe(document.documentElement, { childList: true, subtree: true });

        window.addEventListener('beforeunload', function () {
            if (cleanupTimer) {
                window.clearTimeout(cleanupTimer);
            }
            observer.disconnect();
        });
    }

    var PMStyle = {
        setDocumentOverflowHidden: function (isHidden) {
            document.documentElement.style.overflow = isHidden ? 'hidden' : '';
        },
        applyUsersPickerStyles: function (overlay, panel, search, list, hint) {
            overlay.style.position = 'fixed';
            overlay.style.inset = '0';
            overlay.style.background = 'rgba(0,0,0,0.05)';
            overlay.style.zIndex = '9999';

            panel.style.position = 'absolute';
            panel.style.maxHeight = '400px';
            panel.style.width = '420px';
            panel.style.background = '#fff';
            panel.style.border = '1px solid #ccc';
            panel.style.borderRadius = '4px';
            panel.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
            panel.style.display = 'flex';
            panel.style.flexDirection = 'column';

            search.style.margin = '8px';
            list.style.flex = '1';
            list.style.overflow = 'auto';
            hint.style.padding = '6px 8px';
        },
        positionUsersPickerPanel: function (panelEl, top, left) {
            panelEl.style.top = top + 'px';
            panelEl.style.left = left + 'px';
        },
        toggleDisplay: function (el, show) {
            el.style.display = show ? 'block' : 'none';
        },
        toggleFallbackModal: function (modalEl, show) {
            if (show) {
                modalEl.classList.add('show');
                modalEl.style.display = 'block';
                return;
            }
            modalEl.classList.remove('show');
            modalEl.style.display = 'none';
        },
        setDropdownMenuZIndex: function (menu, zIndex) {
            menu.style.zIndex = String(zIndex);
        },
        applyCosmicCanvasStyles: function (canvas, mountNode, zIndex) {
            canvas.style.position = mountNode === document.body ? 'fixed' : 'absolute';
            canvas.style.inset = '0';
            canvas.style.width = '100%';
            canvas.style.height = '100%';
            canvas.style.pointerEvents = 'none';
            canvas.style.zIndex = String(zIndex);
        },
        ensureRelativePosition: function (mountNode) {
            mountNode.style.position = 'relative';
        },
        setColorScheme: function (themeKey) {
            document.documentElement.style.colorScheme = themeKey === 'ice' ? 'only light' : 'only dark';
        }
    };

    window.PMStyle = PMStyle;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startCleanupObserver);
    } else {
        startCleanupObserver();
    }
})();
