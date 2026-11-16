# Activity Diagram UML Tracer Study UKDW

Folder ini menyimpan source PlantUML dan indeks dokumentasi proses bisnis. Dokumen naratif utama ada di ../../activitydiagram.md.

## Struktur

- INVENTARIS-FITUR.md: audit modul, fitur, aktor, route, dan data.
- MATRIKS-HAK-AKSES.md: hak akses berdasarkan middleware dan scope controller.
- DAFTAR-ACTIVITY-DIAGRAM.md: katalog seluruh activity diagram, termasuk diagram turunan yang sengaja dipisahkan.
- TRACEABILITY-MATRIX.md: hubungan diagram dengan path, class, method, route, service, dan model.
- TEMUAN-ANALISIS.md: batas implementasi dan area yang perlu diuji atau diperjelas.
- AD-XX-*.puml: source PlantUML tiap proses.

## Konvensi

Setiap file memakai partition/swimlane aktor, backend, database, mail, provider, atau Apify sesuai proses. Guard ditulis pada cabang keputusan. Cabang gagal berhenti pada error atau kembali ke form; tidak diarahkan ke sukses.

Notasi activity diagram mengacu pada Sundaramoorthy, S. (2022), *UML Diagramming: A Case Study Approach*, CRC Press: initial node, action, decision ber-guard, swimlane, dan activity final. Satu file menggambarkan satu tujuan proses; login, lupa/reset password, perubahan password wajib, undangan evaluasi, dan evaluasi publik tidak digabung.

Hasil render PNG dan SVG berada di `../../images/activity-diagrams/`. Source PlantUML dan hasil render harus diperbarui bersama apabila alur berubah.

## Rendering

Contoh setelah PlantUML tersedia:

    plantuml -tsvg AD-01-login-alumni.puml
    plantuml -tpng AD-01-login-alumni.puml

Gunakan source pada folder ini sebagai artefak normatif; gambar pada `docs/images/activity-diagrams` adalah hasil render source tersebut.
