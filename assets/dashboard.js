
        document.addEventListener('DOMContentLoaded', function () {
            var splideEl = document.getElementById('shedSplide');
            var splide = null;
            if (splideEl) {
                splide = new Splide(splideEl, {
                    type: 'loop',
                    autoplay: true,
                    interval: 8000,
                    pauseOnHover: false,
                    pauseOnFocus: false,
                    arrows: false,
                    pagination: false,
                    speed: 800,
                });

                splide.mount();
            }

            var enterBtn = document.getElementById('shed-enter-fullscreen');
            var exitBtn = document.getElementById('shed-exit-fullscreen');
            var exitDashboardBtn = document.getElementById('shed-exit-dashboard');
            var editDashboardItemBtn = document.getElementById('shed-edit-dashboard-item');
            var menuToggleBtn = document.getElementById('shed-tv-menu-toggle');
            var menuPanel = document.getElementById('shed-tv-menu-panel');
            var prevSlideBtn = document.getElementById('shed-tv-prev-slide');
            var nextSlideBtn = document.getElementById('shed-tv-next-slide');
            var toggleAutoplayBtn = document.getElementById('shed-tv-toggle-autoplay');
            var printDashboardItemBtn = document.getElementById('shed-print-dashboard-item');
            var isAutoplayPaused = false;

            if (menuToggleBtn && menuPanel) {
                menuToggleBtn.addEventListener('click', function () {
                    var isOpen = menuPanel.classList.toggle('is-open');
                    menuToggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                });
            }

            if (enterBtn) {
                enterBtn.addEventListener('click', function () {
                    if (document.documentElement.requestFullscreen) {
                        document.documentElement.requestFullscreen();
                    }
                });
            }

            if (exitBtn) {
                exitBtn.addEventListener('click', function () {
                    if (document.fullscreenElement && document.exitFullscreen) {
                        document.exitFullscreen();
                    }
                });
            }

            if (exitDashboardBtn) {
                exitDashboardBtn.addEventListener('click', function () {
                    if (document.fullscreenElement && document.exitFullscreen) {
                        document.exitFullscreen();
                    }

                    window.location.href = '/';
                });
            }

            function pauseAutoplay() {
                if (splide && splide.Components && splide.Components.Autoplay) {
                    splide.Components.Autoplay.pause();
                }

                isAutoplayPaused = true;

                if (toggleAutoplayBtn) {
                    toggleAutoplayBtn.textContent = 'Resume';
                    toggleAutoplayBtn.setAttribute('aria-pressed', 'true');
                    toggleAutoplayBtn.classList.add('is-paused');
                }
            }

            function resumeAutoplay() {
                if (splide && splide.Components && splide.Components.Autoplay) {
                    splide.Components.Autoplay.play();
                }

                isAutoplayPaused = false;

                if (toggleAutoplayBtn) {
                    toggleAutoplayBtn.textContent = 'Pause';
                    toggleAutoplayBtn.setAttribute('aria-pressed', 'false');
                    toggleAutoplayBtn.classList.remove('is-paused');
                }
            }

            if (prevSlideBtn) {
                prevSlideBtn.addEventListener('click', function () {
                    if (splide) {
                        splide.go('<');
                    }
                });
            }

            if (nextSlideBtn) {
                nextSlideBtn.addEventListener('click', function () {
                    if (splide) {
                        splide.go('>');
                    }
                });
            }

            if (toggleAutoplayBtn) {
                toggleAutoplayBtn.addEventListener('click', function () {
                    if (isAutoplayPaused) {
                        resumeAutoplay();
                    } else {
                        pauseAutoplay();
                    }
                });
            }

            function getCurrentProjectId() {
                if (!splideEl) {
                    return '';
                }

                var currentSlide = splideEl.querySelector('.splide__slide.is-active .shed-tv-slide');
                return currentSlide ? currentSlide.getAttribute('data-project-id') : '';
            }

            function getCurrentSlide() {
                if (!splideEl) {
                    return null;
                }

                return splideEl.querySelector('.splide__slide.is-active .shed-tv-slide');
            }

            function getCurrentProjectType() {
                var currentSlide = getCurrentSlide();
                return currentSlide ? (currentSlide.getAttribute('data-project-type') || '') : '';
            }

            function getCurrentPrintData() {
                var currentSlide = getCurrentSlide();
                if (!currentSlide) {
                    return null;
                }

                var payloadNode = currentSlide.querySelector('.shed-tv-print-data');
                if (!payloadNode) {
                    return null;
                }

                try {
                    return JSON.parse(payloadNode.textContent || '{}');
                } catch (error) {
                    return null;
                }
            }

            function escapeHtml(value) {
                return String(value === null || value === undefined ? '' : value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function renderPrintRows(rows, columns, emptyMessage) {
                if (!rows || !rows.length) {
                    return '<tr><td colspan="' + columns.length + '">' + escapeHtml(emptyMessage) + '</td></tr>';
                }

                return rows.map(function (row) {
                    var cells = columns.map(function (column) {
                        return '<td>' + escapeHtml(row[column] || '') + '</td>';
                    }).join('');

                    return '<tr>' + cells + '</tr>';
                }).join('');
            }

            function buildProjectPrintHtml(data) {
                var tasksRows = renderPrintRows(
                    data.tasks || [],
                    ['done', 'task', 'est_hours', 'volunteer_name'],
                    'No tasks recorded'
                );

                var costingsRows = renderPrintRows(
                    data.costings || [],
                    ['item', 'qty', 'unit_price', 'line_total'],
                    'No costings recorded'
                );

                var imageBlock = data.image_url
                    ? '<div class="print-image"><img src="' + escapeHtml(data.image_url) + '" alt=""></div>'
                    : '<div class="print-image print-image-empty">No image recorded</div>';

                return [
                    '<!doctype html>',
                    '<html><head><meta charset="utf-8"><title>' + escapeHtml(data.title || 'Project printout') + '</title>',
                    '<style>',
                    'body{font-family:Arial,sans-serif;color:#111;margin:24px;}',
                    'h1{font-size:30px;margin:0 0 8px;}',
                    'h2{font-size:20px;margin:24px 0 10px;}',
                    '.meta{display:grid;grid-template-columns:220px 1fr;gap:8px 16px;margin-bottom:20px;}',
                    '.meta-label{font-weight:700;}',
                    '.description{white-space:pre-wrap;line-height:1.45;border:1px solid #ddd;padding:14px;border-radius:10px;background:#fafafa;}',
                    '.print-image{margin:20px 0 24px;border:1px solid #ddd;border-radius:10px;padding:10px;background:#fafafa;text-align:center;}',
                    '.print-image img{max-width:100%;height:auto;display:block;margin:0 auto;}',
                    '.print-image-empty{padding:32px;color:#666;}',
                    'table{width:100%;border-collapse:collapse;margin-top:8px;}',
                    'th,td{border:1px solid #ccc;padding:8px 10px;text-align:left;vertical-align:top;}',
                    'th{background:#f0f0f0;font-weight:700;}',
                    '.grand-total{margin-top:10px;text-align:right;font-size:18px;font-weight:700;}',
                    '@media print{body{margin:12mm;}}',
                    '</style></head><body>',
                    '<h1>' + escapeHtml(data.title || '') + '</h1>',
                    '<div class="meta">',
                    '<div class="meta-label">Project reference</div><div>' + escapeHtml(data.project_ref || 'Not set') + '</div>',
                    '<div class="meta-label">Target date</div><div>' + escapeHtml(data.target || 'Not set') + '</div>',
                    '<div class="meta-label">Project contact</div><div>' + escapeHtml(data.project_contact || 'Not set') + '</div>',
                    '<div class="meta-label">Project lifecycle</div><div>' + escapeHtml(data.project_lifecycle || 'Not set') + '</div>',
                    '</div>',
                    '<h2>Description</h2>',
                    '<div class="description">' + escapeHtml(data.description || '') + '</div>',
                    '<h2>Notes</h2>',
                    '<div class="description">' + escapeHtml(data.project_notes || 'No notes recorded') + '</div>',
                    '<h2>Image</h2>',
                    imageBlock,
                    '<h2>Tasks</h2>',
                    '<table><thead><tr><th>Done</th><th>Task</th><th>Est hours</th><th>Volunteer name</th></tr></thead><tbody>',
                    tasksRows,
                    '</tbody></table>',
                    '<h2>Project costings</h2>',
                    '<table><thead><tr><th>Item</th><th>Qty</th><th>Unit price</th><th>Total</th></tr></thead><tbody>',
                    costingsRows,
                    '</tbody></table>',
                    '<div class="grand-total">Grand total: £' + escapeHtml(data.costings_grand_total || '0.00') + '</div>',
                    '</body></html>'
                ].join('');
            }

            function updatePrintButtonVisibility() {
                if (!printDashboardItemBtn) {
                    return;
                }

                var isProject = getCurrentProjectType() === 'project';
                printDashboardItemBtn.style.display = isProject ? '' : 'none';
                printDashboardItemBtn.disabled = !isProject;
            }

            function getCurrentVideoUrl() {
                if (!splideEl) {
                    return '';
                }

                var currentSlide = splideEl.querySelector('.splide__slide.is-active .shed-tv-slide');
                return currentSlide ? currentSlide.getAttribute('data-video-url') || '' : '';
            }

            function openVideoUrl(videoUrl) {
                if (!videoUrl) {
                    return;
                }

                if (document.fullscreenElement && document.exitFullscreen) {
                    document.exitFullscreen();
                }

                window.open(videoUrl, '_blank', 'noopener');
            }

            if (editDashboardItemBtn) {
                editDashboardItemBtn.addEventListener('click', function () {
                    var projectId = getCurrentProjectId();
                    var editUrl = editDashboardItemBtn.getAttribute('data-edit-url');

                    if (!projectId || !editUrl) {
                        return;
                    }

                    var url = new URL(editUrl, window.location.origin);
                    url.searchParams.set('project_id', projectId);

                    if (document.fullscreenElement && document.exitFullscreen) {
                        document.exitFullscreen();
                    }

                    window.location.href = url.toString();
                });
            }

            if (printDashboardItemBtn) {
                printDashboardItemBtn.addEventListener('click', function () {
                    var printData = getCurrentPrintData();
                    if (!printData) {
                        return;
                    }

                    var printWindow = window.open('', '_blank', 'width=1100,height=850');
                    if (!printWindow) {
                        return;
                    }

                    printWindow.document.open();
                    printWindow.document.write(buildProjectPrintHtml(printData));
                    printWindow.document.close();
                    printWindow.focus();

                    printWindow.onload = function () {
                        printWindow.print();
                    };
                });
            }

            var typeFilter = document.getElementById('shed-tv-type-filter');
            var statusFilter = document.getElementById('shed-tv-status-filter');
            var currentUrl = new URL(window.location.href);
            var currentType = currentUrl.searchParams.get('tv_type') || (typeFilter ? typeFilter.value : 'project');
            if (currentType === 'videos') {
                currentType = 'video';
            }
            var currentStatus = currentUrl.searchParams.get('tv_status') || (statusFilter ? statusFilter.getAttribute('data-current-status') : '') || defaultStatusForType(currentType);
            var updatedProjectId = currentUrl.searchParams.get('shed_task_project');
            var statusOptionsByType = {
                project: [
                    { value: 'active', label: 'Not complete' },
                    { value: 'quote', label: 'Quote' },
                    { value: 'making', label: 'Making' },
                    { value: 'invoicing', label: 'Invoicing' },
                    { value: 'complete', label: 'Complete' },
                    { value: 'all', label: 'All' }
                ],
                event: [
                    { value: 'open', label: 'Open' },
                    { value: 'ended', label: 'Ended' },
                    { value: 'all', label: 'All' }
                ],
                idea: [
                    { value: 'open', label: 'Open' },
                    { value: 'ended', label: 'Ended' },
                    { value: 'all', label: 'All' }
                ],
                video: [
                    { value: 'active', label: 'Active' },
                    { value: 'archived', label: 'Archived' },
                    { value: 'all', label: 'All' }
                ]
            };

            function defaultStatusForType(type) {
                if (type === 'video') {
                    return 'active';
                }

                if (type === 'event' || type === 'idea') {
                    return 'open';
                }

                return 'active';
            }

            function populateStatusFilter(type, status) {
                if (!statusFilter) {
                    return;
                }

                var options = statusOptionsByType[type] || statusOptionsByType.project;
                var optionValues = options.map(function (option) {
                    return option.value;
                });

                if (optionValues.indexOf(status) === -1) {
                    status = defaultStatusForType(type);
                }

                statusFilter.innerHTML = '';

                options.forEach(function (option) {
                    var optionEl = document.createElement('option');
                    optionEl.value = option.value;
                    optionEl.textContent = option.label;
                    optionEl.selected = option.value === status;
                    statusFilter.appendChild(optionEl);
                });
            }

            if (typeFilter) {
                typeFilter.value = currentType;
                typeFilter.addEventListener('change', function () {
                    var nextType = typeFilter.value || 'project';
                    var url = new URL(window.location.href);
                    url.searchParams.set('tv_type', nextType);
                    url.searchParams.set('tv_status', defaultStatusForType(nextType));
                    url.searchParams.delete('shed_task_updated');
                    url.searchParams.delete('shed_task_project');
                    window.location.href = url.toString();
                });
            }

            populateStatusFilter(currentType, currentStatus);
            updatePrintButtonVisibility();

            if (statusFilter) {
                statusFilter.addEventListener('change', function () {
                    var url = new URL(window.location.href);
                    url.searchParams.set('tv_type', currentType);
                    url.searchParams.set('tv_status', statusFilter.value || defaultStatusForType(currentType));
                    url.searchParams.delete('shed_task_updated');
                    url.searchParams.delete('shed_task_project');
                    window.location.href = url.toString();
                });
            }

            if (splide) {
                splide.on('mounted moved', function () {
                    updatePrintButtonVisibility();
                });
            }

            if (currentUrl.searchParams.get('shed_task_updated')) {
                setTimeout(function () {
                    var notice = document.querySelector('.shed-tv-task-updated');
                    if (notice) {
                        notice.hidden = true;
                    }
                }, 3000);

                currentUrl.searchParams.delete('shed_task_updated');
                currentUrl.searchParams.delete('shed_task_project');
                window.history.replaceState({}, document.title, currentUrl.toString());
            }

            if (updatedProjectId && splide) {
                var allSlides = Array.prototype.slice.call(document.querySelectorAll('#shedSplide .splide__slide:not(.splide__slide--clone)'));
                var targetIndex = allSlides.findIndex(function (slide) {
                    var projectSlide = slide.querySelector('.shed-tv-slide');
                    return projectSlide && projectSlide.getAttribute('data-project-id') === updatedProjectId;
                });

                if (targetIndex >= 0) {
                    splide.go(targetIndex);
                }
            }

            document.querySelectorAll('.shed-tv-task-form input[type="text"]').forEach(function (input) {
                input.addEventListener('focus', function () {
                    if (splide && splide.Components && splide.Components.Autoplay) {
                        splide.Components.Autoplay.pause();
                    }
                });

                input.addEventListener('blur', function () {
                    if (splide && splide.Components && splide.Components.Autoplay) {
                        splide.Components.Autoplay.play();
                    }
                });
            });

            document.querySelectorAll('.shed-tv-video-open, .shed-tv-video-play-overlay').forEach(function (button) {
                button.addEventListener('click', function () {
                    openVideoUrl(button.getAttribute('data-video-url') || getCurrentVideoUrl());
                });
            });

            var descriptionBlocks = document.querySelectorAll('.shed-tv-description');

            descriptionBlocks.forEach(function (description) {
                var toggle = description.parentElement.querySelector('.shed-tv-description-toggle');
                if (!toggle) {
                    return;
                }

                description.classList.add('is-collapsed');
                toggle.setAttribute('aria-expanded', 'false');

                function updateToggleVisibility() {
                    var wasExpanded = description.classList.contains('is-expanded');

                    if (wasExpanded) {
                        description.classList.remove('is-expanded');
                    }

                    description.classList.add('is-collapsed');

                    var isOverflowing = description.scrollHeight > description.clientHeight + 2;

                    if (!isOverflowing && description.textContent.trim().length > 140) {
                        isOverflowing = true;
                    }

                    if (wasExpanded) {
                        description.classList.remove('is-collapsed');
                        description.classList.add('is-expanded');
                    }

                    toggle.hidden = !isOverflowing && !wasExpanded;
                }

                toggle.addEventListener('click', function () {
                    var expanded = !description.classList.contains('is-expanded');

                    description.classList.toggle('is-expanded', expanded);
                    description.classList.toggle('is-collapsed', !expanded);
                    toggle.textContent = expanded ? 'Less' : 'More';
                    toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                    updateToggleVisibility();
                });

                requestAnimationFrame(updateToggleVisibility);
                window.addEventListener('load', updateToggleVisibility);
                window.addEventListener('resize', updateToggleVisibility);
            });

            setTimeout(function () {
                location.reload();
            }, 300000);
        });
