{{--
    Arabic typeface for the Qur'an pages only.

    Loaded here rather than in the global stylesheet because it is a large file
    that every other page would pay for without using a single glyph of it.

    Scheherazade New is chosen over a system fallback for one practical reason:
    Qur'anic text carries far more diacritics than ordinary Arabic, and fonts not
    designed for it collide the marks into each other at reading size.
--}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet"
      href="https://fonts.googleapis.com/css2?family=Scheherazade+New:wght@400;700&display=swap">
