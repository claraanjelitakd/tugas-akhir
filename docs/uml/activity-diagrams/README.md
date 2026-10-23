# Activity Diagram UML Tracer Study UKDW

Folder ini menyimpan source PlantUML dan indeks dokumentasi proses bisnis. Dokumen naratif utama ada di ../../activitydiagram.md.

## Struktur

- INVENTARIS-FITUR.md: audit modul, fitur, aktor, route, dan data.
- MATRIKS-HAK-AKSES.md: hak akses berdasarkan middleware dan scope controller.
- DAFTAR-ACTIVITY-DIAGRAM.md: katalog AD-01 sampai AD-24.
- TRACEABILITY-MATRIX.md: hubungan diagram dengan path, class, method, route, service, dan model.
- TEMUAN-ANALISIS.md: batas implementasi dan area yang perlu diuji atau diperjelas.
- AD-XX-*.puml: source PlantUML tiap proses.

## Konvensi

Setiap file memakai partition/swimlane aktor, backend, database, mail, provider, atau Apify sesuai proses. Guard ditulis pada cabang keputusan. Cabang gagal berhenti pada error atau kembali ke form; tidak diarahkan ke sukses.

PlantUML CLI/JAR tidak tersedia saat audit. Karena itu SVG/PNG baru belum dibuat. Source tetap disediakan agar dapat dirender dengan PlantUML di lingkungan yang memiliki renderer.

## Rendering

Contoh setelah PlantUML tersedia:

    plantuml -tsvg AD-01-login-alumni.puml
    plantuml -tpng AD-01-login-alumni.puml

Jangan menganggap gambar lama di docs/images sebagai hasil render dari source pada folder ini.
