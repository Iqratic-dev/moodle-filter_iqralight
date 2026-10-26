# Iqralight: tap to load — Moodle filter

Companion of the Iqralight theme for learners with very weak, costly or unstable
connections. Heavy media wait for the learner's tap, with their size shown.

**Version 0.1.0 (alpha)** — Moodle 5.0 and later. Target: Moodle 5.3 LTS.

## What it does

- Players of known video platforms (YouTube, Vimeo, Dailymotion, Kaltura,
  Panopto, TED, Wistia) and PDF documents embedded in content are replaced
  by a placeholder: a button with the size when known, and a direct link.
- Images stored in Moodle above the threshold (300 KB by default) wait for a
  tap; lighter images are only marked for the theme's *Text only* option.
- Videos and sounds hosted in Moodle get `preload="none"`: nothing is
  downloaded before the learner presses play.
- With the Iqralight theme in *Load everything* mode, everything loads straight
  away. Without the theme, every deferred media waits for a tap.

## Video and sound players

Players built by Moodle's Video.js player also wait for a tap, with the size of the
file: neither the video nor the player's script (about 240 KB) is downloaded before
the learner asks for it. Once loaded, the video still starts only when played.

## Filter order

This filter must come **after every other filter**, in particular after *Multimedia
plugins*, which builds the players. It moves itself to the last place when installed
or upgraded. If you add or reorder filters later, keep it last in
*Site administration > Plugins > Filters > Manage filters*.

## Prudence rules

- Only a short allow-list of elements is touched; anything else, and any
  doubt, leaves the text byte-for-byte unchanged.
- Quizzes, question banks, H5P and virtual classrooms are always excluded.
  More activity types can be excluded in the filter settings, and a whole
  course by turning the filter off in that course.
- The output does not depend on the learner's mode, so Moodle's text cache
  stays valid for everyone.

## Installation

Unzip into `filter/iqralight`, run the upgrade. The filter is enabled on
installation and placed after *Multimedia plugins*.

## Licence

GNU GPL v3 or later. © 2026 IQRATIC.
