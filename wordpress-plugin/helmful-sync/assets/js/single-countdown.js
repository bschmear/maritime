(function () {
    function pad(value) {
        return String(value).padStart(2, '0');
    }

    function tick(element) {
        var target = element.getAttribute('data-target');
        if (!target) {
            return;
        }

        var targetMs = Date.parse(target);
        if (Number.isNaN(targetMs)) {
            return;
        }

        var diff = targetMs - Date.now();
        var elapsed = element.querySelector('.helmful-countdown__elapsed');
        var grid = element.querySelector('.helmful-countdown__grid');

        if (diff <= 0) {
            element.classList.add('helmful-countdown--elapsed');
            if (grid) {
                grid.hidden = true;
            }
            if (elapsed) {
                elapsed.hidden = false;
            }

            return;
        }

        var totalSeconds = Math.floor(diff / 1000);
        var days = Math.floor(totalSeconds / 86400);
        totalSeconds -= days * 86400;
        var hours = Math.floor(totalSeconds / 3600);
        totalSeconds -= hours * 3600;
        var minutes = Math.floor(totalSeconds / 60);
        var seconds = totalSeconds - minutes * 60;

        var units = {
            days: String(days),
            hours: pad(hours),
            minutes: pad(minutes),
            seconds: pad(seconds),
        };

        Object.keys(units).forEach(function (unit) {
            var node = element.querySelector('[data-unit="' + unit + '"]');
            if (node) {
                node.textContent = units[unit];
            }
        });
    }

    function init() {
        document.querySelectorAll('[data-helmful-countdown]').forEach(function (element) {
            tick(element);
            window.setInterval(function () {
                tick(element);
            }, 1000);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
