
        document.addEventListener('DOMContentLoaded', function () {
            var splideEl = document.getElementById('shedSplide');
            if (splideEl) {
                new Splide(splideEl, {
                    type: 'loop',
                    autoplay: true,
                    interval: 8000,
                    pauseOnHover: false,
                    pauseOnFocus: false,
                    arrows: false,
                    pagination: false,
                    speed: 800,
                }).mount();
            }

            var enterBtn = document.getElementById('shed-enter-fullscreen');
            var exitBtn = document.getElementById('shed-exit-fullscreen');
            var exitDashboardBtn = document.getElementById('shed-exit-dashboard');

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

            var filterButtons = document.querySelectorAll('.shed-filter-btn');
            var currentUrl = new URL(window.location.href);
            var currentFilter = currentUrl.searchParams.get('tv_filter') || 'all';

            function setActiveFilterButton(filter) {
                filterButtons.forEach(function (btn) {
                    btn.classList.toggle('active', btn.getAttribute('data-filter') === filter);
                });
            }

            setActiveFilterButton(currentFilter);

            if (!currentUrl.searchParams.get('tv_filter')) {
                var savedFilter = localStorage.getItem('shed_tv_filter');
                if (savedFilter && savedFilter !== 'all') {
                    currentUrl.searchParams.set('tv_filter', savedFilter);
                    window.location.replace(currentUrl.toString());
                    return;
                }
            }

            filterButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var filter = btn.getAttribute('data-filter') || 'all';
                    localStorage.setItem('shed_tv_filter', filter);

                    var url = new URL(window.location.href);

                    if (filter === 'all') {
                        url.searchParams.delete('tv_filter');
                    } else {
                        url.searchParams.set('tv_filter', filter);
                    }

                    window.location.href = url.toString();
                });
            });

            setTimeout(function () {
                location.reload();
            }, 300000);
        });