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

    function pad2(value) {
        return String(value).padStart(2, '0');
    }

    function parseAdminDateTime(value) {
        var match = String(value || '').trim().match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/);
        if (!match) {
            return new Date();
        }

        return new Date(
            Number(match[1]),
            Number(match[2]) - 1,
            Number(match[3]),
            Number(match[4] || 0),
            Number(match[5] || 0),
            Number(match[6] || 0)
        );
    }

    function formatAdminDateTime(date, hour, minute) {
        return [
            date.getFullYear(),
            '-',
            pad2(date.getMonth() + 1),
            '-',
            pad2(date.getDate()),
            ' ',
            pad2(hour),
            ':',
            pad2(minute),
            ':00'
        ].join('');
    }

    function initDateTimePickers() {
        var inputs = document.querySelectorAll('[data-pm-datetime-picker]');
        if (!inputs.length) {
            return;
        }

        var activeInput = null;
        var selectedDate = new Date();
        var viewDate = new Date(selectedDate.getFullYear(), selectedDate.getMonth(), 1);
        var popover = document.createElement('div');
        popover.className = 'pm-datetime-popover';
        popover.hidden = true;
        popover.innerHTML = [
            '<div class="pm-datetime-head">',
            '<button class="pm-datetime-nav" type="button" data-pm-dt-prev aria-label="Previous month">&lsaquo;</button>',
            '<div class="pm-datetime-title" data-pm-dt-title></div>',
            '<button class="pm-datetime-nav" type="button" data-pm-dt-next aria-label="Next month">&rsaquo;</button>',
            '</div>',
            '<div class="pm-datetime-week"><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span><span>Su</span></div>',
            '<div class="pm-datetime-grid" data-pm-dt-grid></div>',
            '<div class="pm-datetime-time">',
            '<input type="number" min="0" max="23" step="1" data-pm-dt-hour aria-label="Hour">',
            '<span>:</span>',
            '<input type="number" min="0" max="59" step="1" data-pm-dt-minute aria-label="Minute">',
            '</div>',
            '<div class="pm-datetime-actions">',
            '<button class="pm-datetime-action" type="button" data-pm-dt-clear>Clear</button>',
            '<button class="pm-datetime-action" type="button" data-pm-dt-today>Today</button>',
            '<button class="pm-datetime-action" type="button" data-pm-dt-apply>Apply</button>',
            '</div>'
        ].join('');
        document.body.appendChild(popover);

        var title = popover.querySelector('[data-pm-dt-title]');
        var grid = popover.querySelector('[data-pm-dt-grid]');
        var hourInput = popover.querySelector('[data-pm-dt-hour]');
        var minuteInput = popover.querySelector('[data-pm-dt-minute]');

        function sameDay(a, b) {
            return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
        }

        function render() {
            var today = new Date();
            var monthName = viewDate.toLocaleString('en', { month: 'long', year: 'numeric' });
            var first = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1);
            var startOffset = (first.getDay() + 6) % 7;
            var start = new Date(first);
            start.setDate(first.getDate() - startOffset);

            title.textContent = monthName;
            grid.innerHTML = '';

            for (var i = 0; i < 42; i += 1) {
                var day = new Date(start);
                day.setDate(start.getDate() + i);

                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'pm-datetime-day';
                button.textContent = String(day.getDate());
                button.dataset.year = String(day.getFullYear());
                button.dataset.month = String(day.getMonth());
                button.dataset.day = String(day.getDate());

                if (day.getMonth() !== viewDate.getMonth()) {
                    button.classList.add('is-muted');
                }
                if (sameDay(day, today)) {
                    button.classList.add('is-today');
                }
                if (sameDay(day, selectedDate)) {
                    button.classList.add('is-selected');
                }

                grid.appendChild(button);
            }

            hourInput.value = pad2(selectedDate.getHours());
            minuteInput.value = pad2(selectedDate.getMinutes());
        }

        function place() {
            if (!activeInput) {
                return;
            }
            var rect = activeInput.getBoundingClientRect();
            var width = popover.offsetWidth || 360;
            var left = Math.min(Math.max(12, rect.left), window.innerWidth - width - 12);
            var top = rect.bottom + 8;
            if (top + popover.offsetHeight > window.innerHeight - 12) {
                top = Math.max(12, rect.top - popover.offsetHeight - 8);
            }
            popover.style.left = left + 'px';
            popover.style.top = top + 'px';
        }

        function open(input) {
            activeInput = input;
            selectedDate = parseAdminDateTime(input.value);
            viewDate = new Date(selectedDate.getFullYear(), selectedDate.getMonth(), 1);
            popover.hidden = false;
            render();
            place();
        }

        function close() {
            popover.hidden = true;
            activeInput = null;
        }

        function apply() {
            if (!activeInput) {
                return;
            }
            var hour = Math.min(23, Math.max(0, Number(hourInput.value || 0)));
            var minute = Math.min(59, Math.max(0, Number(minuteInput.value || 0)));
            activeInput.value = formatAdminDateTime(selectedDate, hour, minute);
            activeInput.dispatchEvent(new Event('input', { bubbles: true }));
            activeInput.dispatchEvent(new Event('change', { bubbles: true }));
            close();
        }

        popover.addEventListener('click', function (event) {
            var target = event.target;
            if (!target || !target.closest) {
                return;
            }
            if (target.closest('[data-pm-dt-prev]')) {
                viewDate.setMonth(viewDate.getMonth() - 1);
                render();
            } else if (target.closest('[data-pm-dt-next]')) {
                viewDate.setMonth(viewDate.getMonth() + 1);
                render();
            } else if (target.closest('[data-pm-dt-today]')) {
                selectedDate = new Date();
                viewDate = new Date(selectedDate.getFullYear(), selectedDate.getMonth(), 1);
                render();
            } else if (target.closest('[data-pm-dt-clear]')) {
                if (activeInput) {
                    activeInput.value = '';
                    activeInput.dispatchEvent(new Event('input', { bubbles: true }));
                    activeInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
                close();
            } else if (target.closest('[data-pm-dt-apply]')) {
                apply();
            } else if (target.closest('.pm-datetime-day')) {
                selectedDate = new Date(
                    Number(target.dataset.year),
                    Number(target.dataset.month),
                    Number(target.dataset.day),
                    Number(hourInput.value || selectedDate.getHours()),
                    Number(minuteInput.value || selectedDate.getMinutes()),
                    0
                );
                render();
            }
        });

        for (var i = 0; i < inputs.length; i += 1) {
            inputs[i].addEventListener('focus', function () {
                open(this);
            });
            inputs[i].addEventListener('click', function () {
                open(this);
            });
        }

        document.addEventListener('mousedown', function (event) {
            if (!activeInput || popover.contains(event.target) || event.target === activeInput) {
                return;
            }
            close();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && activeInput) {
                close();
            }
            if (event.key === 'Enter' && activeInput && popover.contains(document.activeElement)) {
                event.preventDefault();
                apply();
            }
        });

        window.addEventListener('resize', place);
        window.addEventListener('scroll', place, true);
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
        document.addEventListener('DOMContentLoaded', function () {
            startCleanupObserver();
            initDateTimePickers();
        });
    } else {
        startCleanupObserver();
        initDateTimePickers();
    }
})();
