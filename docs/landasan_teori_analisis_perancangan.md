# Landasan Teori Analisis dan Perancangan Perangkat Lunak

Dokumen ini merupakan ringkasan komprehensif landasan teori analisis dan perancangan perangkat lunak yang dijadikan standar baku dalam perancangan arsitektur, diagram alir data, diagram use case, serta struktur basis data Sistem Informasi Tracer Study Universitas Kristen Duta Wacana (UKDW). Dokumen ini mengacu secara langsung pada berkas pustaka referensi akademik yang tersimpan dalam repositori (`sumber uml/`):

1. **Kenneth E. Kendall & Julie E. Kendall**, *Systems Analysis and Design*, Tenth Edition, Global Edition, Pearson (2020), Chapter 7: *Using Data Flow Diagrams* (hlm. 224–254).
2. **Dr. Suriya Sundaramoorthy**, *UML Diagramming: A Case Study Approach*, CRC Press / Taylor & Francis Group (2022), Chapter 1: *Introduction to UML Diagrams and Its Components* (hlm. 1–22).
3. **Standar Rekayasa Perangkat Lunak Basis Data Relasional & Object-Relational Mapping (ORM)**.

---

## 1. Teori Use Case Diagram & Unified Modeling Language (UML)
*Sumber: Dr. Suriya Sundaramoorthy (2022, hlm. 1–7)*

### 1.1 Metodologi Unified Process (UP)
Dr. Suriya Sundaramoorthy menguraikan bahwa pembangunan sistem berorientasi objek (*Object-Oriented System*) dijalankan melalui siklus hidup berulang (*iterative lifecycle*) bernama **Unified Process (UP)** yang terdiri atas 4 fase utama:
1. **Inception Phase**: Mengkaji kebutuhan awal dari klien dan menjawab 7 pertanyaan kelayakan bisnis/teknis untuk memutuskan kelayakan pengembangan perangkat lunak.
2. **Elaboration Phase**: Mengambil laporan fase inception untuk merancang arsitektur inti sistem (*core architecture*), jadwal pengerjaan proyek (*project schedule*), dan pemetaan kebutuhan fungsional ke dalam **UML Use Case Diagram**.
3. **Construction Phase**: Melaksanakan aktivitas pemrograman (*coding*), pengujian internal pengembang (*unit testing* dan *alpha testing*), serta pengumpulan umpan balik.
4. **Transition Phase**: Melaksanakan pengujian pengguna di lokasi klien tanpa campur tangan langsung pengembang (*beta testing*), peluncuran (*deployment*), dan penyempurnaan akhir (*fine-tuning*).

### 1.2 Taksonomi Diagram UML
Unified Modeling Language (UML) merupakan bahasa pemodelan visual grafis standar industri untuk menspesifikasikan, memvisualisasikan, membangun, dan mendokumentasikan artefak sistem perangkat lunak. Menurut Sundaramoorthy (Tabel 1.1), diagram UML terbagi menjadi:
- **Diagram Perilaku (*Behavioral Diagrams*)**:
  - **UML Use Case Diagram**: Berfokus pada identifikasi kebutuhan fungsional diskret sistem dari sudut pandang aktor eksternal.
  - **UML Activity Diagram**: Berfokus pada urutan aktivitas sekuensial maupun paralel dalam setiap skenario fungsional.
  - **UML State Machine Diagram**: Menggambarkan siklus hidup suatu objek melalui status (*states*), transisi (*transitions*), dan pemicu (*events*).
  - **UML Sequence & Collaboration Diagram**: Menggambarkan interaksi antar-objek dan urutan pertukaran pesan secara kronologis.
- **Diagram Struktural (*Structural Diagrams*)**:
  - **UML Class Diagram**: Mendeskripsikan struktur statis sistem dalam bentuk kelas-kelas, atribut, metode, dan relasi antarkelas.
  - **UML Component Diagram**: Menunjukkan hubungan fisik dan logis antar-komponen modular perangkat lunak.
  - **UML Deployment Diagram**: Memetakan arsitektur perangkat keras fisik dan simpul runtime tempat komponen perangkat lunak disebarkan.

### 1.3 Komponen Pokok Use Case Diagram (Sundaramoorthy Tabel 1.2)
1. **Batas Sistem (*System Boundary*)**: Kotak pembatas yang melingkupi seluruh fungsionalitas internal sistem dan memisahkan sistem dari dunia luar.
2. **Aktor (*Actors*)**: Entitas luar yang berinteraksi dengan sistem, dapat berupa **manusia (*person*)**, **organisasi (*organization*)**, atau **sistem luar (*outside system*)**.
3. **Kasus Penggunaan (*Usecases*)**: Representasi visual berbentuk elips yang melambangkan fungsi bisnis diskret yang memberikan hasil terukur bagi aktor.

### 1.4 Relasi Antar-Komponen Use Case (Sundaramoorthy Tabel 1.3)
1. **Asosiasi (*Association*)**: Garis komunikasi dua arah antara aktor dan use case.
2. **Asosiasi Terarah (*Directed Association*)**: Garis berpanah terbuka satu arah yang menandakan aktor memiliki tanggung jawab memicu jalannya use case.
3. **Relasi Include (`<<include>>`)**: Relasi ketergantungan wajib di mana use case dasar menyertakan fungsionalitas use case lain agar proses dapat tuntas.
4. **Relasi Extend (`<<extend>>`)**: Relasi perluasan opsional/kondisional di mana use case tambahan menyisipkan perilakunya ke use case dasar pada titik ekstensi (*extension point*) tertentu jika kondisi pemicu terpenuhi.
5. **Generalisasi (*Generalization*)**: Relasi pewarisan sifat dari aktor/use case induk (*parent*) ke aktor/use case turunan (*child*), dilambangkan dengan panah segitiga kosong tertutup.
6. **Ketergantungan (*Dependency*)**: Relasi di mana keberadaan suatu use case bergantung pada fungsionalitas use case lainnya.

### 1.5 Format Standar Tabel Spesifikasi Use Case (Sundaramoorthy Subbab 1.1.2.1)
Setiap use case wajib dirinci menggunakan templat formal 9 elemen:
- `Usecase ID`: Pengenal unik use case (misal: UC01).
- `Usecase Name`: Nama fungsional use case.
- `Actors Involved`: Aktor-aktor yang terlibat.
- `Description`: Uraian fungsionalitas bisnis use case.
- `Preconditions`: Kondisi awal sebelum fungsionalitas dijalankan.
- `Main / Normal Flow`: Urutan langkah keberhasilan proses utama.
- `Postconditions`: Kondisi akhir sistem dan status data setelah eksekusi.
- `Alternate Flow`: Jalur alternatif yang sah di luar alur utama.
- `Exception Flow`: Kondisi kegagalan sistem, validasi tidak terpenuhi, atau pelanggaran keamanan.

---

## 2. Teori Data Flow Diagram (DFD)
*Sumber: Kenneth E. Kendall & Julie E. Kendall (2020, Chapter 7, hlm. 224–254)*

### 2.1 Pendekatan Aliran Data (*Data Flow Approach*)
Kendall & Kendall menekankan bahwa Data Flow Diagram (DFD) memberikan 4 keuntungan utama dibanding narasi verbal:
1. Memberikan kebebasan konseptual tanpa terjebak terlalu dini pada komitmen implementasi teknis tertentu (*freedom from committing to technical implementation too early*).
2. Membantu memahami keterkaitan mendalam antarsistem dan subsistem (*further understanding of the interrelatedness of systems*).
3. Mengomunikasikan pengetahuan sistem saat ini kepada pengguna secara visual (*communicating system knowledge*).
4. Menganalisis sistem usulan untuk memastikan bahwa seluruh data masukan dan proses transformasi telah terdefinisi secara utuh.

### 2.2 Empat Simbol Dasar DFD (Kendall hlm. 226–227, Figure 7.1)
1. **Entitas Eksternal (*External Entity / Source & Destination*)**:
   - Notasi: **Kotak Ganda (*Double Square*)**.
   - Merepresentasikan asal atau tujuan data di luar batas sistem (orang, departemen, bisnis, atau sistem/mesin lain).
   - Wajib dinamai dengan **Kata Benda (*Noun*)**. Entitas yang sama dapat digambar berulang untuk mencegah perpotongan garis (*crossing lines*).
2. **Aliran Data (*Data Flow*)**:
   - Notasi: **Panah (*Arrow*)**.
   - Menunjukkan pergerakan data dari satu titik ke titik lain dengan kepala panah menuju ke arah tujuan.
   - Wajib dinamai dengan **Kata Benda (*Noun*)** yang spesifik (bukan nama dokumen fisik umum).
3. **Proses Transformasi (*Transforming Process*)**:
   - Notasi: **Persegi Bersudut Tumpul (*Rectangle with Rounded Corners*)**.
   - Menunjukkan terjadinya perubahan bentuk (*transformation*) data. Data keluar dari proses wajib dinamai berbeda dari data yang masuk.
   - Wajib dinamai dengan formula **Kata Kerja – Kata Sifat – Kata Benda (*Verb–Adjective–Noun*)**, misal: `Validasi Profil Alumni`, `Proses Kuesioner Tracer`.
   - Diberi nomor pengenal unik (0 untuk Konteks, bilangan bulat 1, 2, dst. untuk Diagram 0, dan bilangan berdesimal 1.1, 1.2 untuk Diagram Anak).
4. **Simpanan Data Logis (*Data Store*)**:
   - Notasi: **Persegi Terbuka di Sisi Kanan (*Open-Ended Rectangle*)**.
   - Menunjukkan tempat penyimpanan data (database, berkas komputer, arsip master). Diberi nomor referensi unik (`D1`, `D2`, `D3`, dst.) dan dinamai dengan **Kata Benda (*Noun*)**.

### 2.3 Aturan Integritas dan Sintaksis DFD (Kendall hlm. 227–232)
1. **Integritas Aliran Data**:
   - Setiap proses wajib memiliki minimal satu masukan (*input*) dan satu keluaran (*output*).
   - Dilarang membuat **Black Hole** (proses menerima masukan tetapi tidak menghasilkan keluaran).
   - Dilarang membuat **Miracle** (proses menghasilkan keluaran tanpa menerima masukan apa pun).
   - Dilarang membuat **Gray Hole** (proses menghasilkan keluaran yang mustahil dibentuk dari masukan yang diterima).
2. **Koneksi Simpanan Data dan Entitas**:
   - Entitas eksternal **dilarang terhubung langsung** ke Simpanan Data tanpa proses penengah.
   - Entitas eksternal **dilarang terhubung langsung** ke sesama Entitas Eksternal di dalam diagram sistem.
   - Simpanan Data **dilarang terhubung langsung** ke sesama Simpanan Data tanpa proses transformasi.
3. **Hierarki Diagram & Keseimbangan Vertikal (*Vertical Balancing*)**:
   - **Diagram Konteks (*Context Diagram*)**: Diagram level 0 tertinggi yang hanya memuat proses 0, seluruh entitas eksternal, dan aliran antarmuka. Tidak memuat *data store*.
   - **Diagram 0 (*Diagram 0*)**: Ledakan dari diagram konteks. Dibatasi **antara 3 hingga 9 proses** agar diagram tetap mudah dipahami dan tidak terlalu padat (*cluttered*).
   - **Diagram Anak (*Child Diagrams*)**: Ledakan dari proses tertentu pada Diagram 0 (misal: proses 1 meledak menjadi diagram 1.1, 1.2, dst.).
   - **Prinsip Keseimbangan Vertikal**: Seluruh aliran masukan dan keluaran yang terhubung ke proses induk di Diagram 0 **wajib muncul secara persis** pada diagram anak yang menjelaskannya. Diagram anak tidak boleh menciptakan aliran luar yang tidak tercatat pada proses induk.
4. **Penolakan Aliran Linier (*Avoiding Linear Flow Error*)**:
   - DFD bukan flowchart; aliran data tidak boleh digambar sebagai rangkaian berantai sekuensial tunggal (A $\rightarrow$ B $\rightarrow$ C) melainkan mencerminkan sistem berbasis peristiwa (*event-driven*).

### 2.4 DFD Logis vs DFD Fisik & Partisi Sistem (Kendall hlm. 235–244)
- **DFD Logis (*Logical DFD*)**: Menggambarkan esensi logika proses bisnis tanpa tergantung pada teknologi penyimpanan atau media tertentu.
- **DFD Fisik (*Physical DFD*)**: Memetakan implementasi konkret di dunia nyata: nama tabel basis data fisik (misal: tabel MySQL `users`, `biodata`), mekanisme formulir web, serta pembedaan proses terkomputerisasi versus manual.
- **Partisi Fisik (*Physical Partitioning*)**: Mengelompokkan proses ke dalam modul eksekusi: antarmuka web interaktif, mesin kuesioner terpadu, penanganan integrasi API latar belakang, dan lapisan kueri analitik performa tinggi.

---

## 3. Teori Pemodelan Data: Entity-Relationship Diagram (ERD) & Korelasinya dengan UML Class Diagram
*Sumber Sintesis: Dr. Suriya Sundaramoorthy (2022) & Standar Relasional*

### 3.1 Posisi ERD dalam Literatur Pemodelan
Kajian terhadap berkas pustaka `sumber uml/Dr. Suriya Sundaramoorthy (2022)` menunjukkan bahwa buku tersebut merupakan pustaka pemodelan **Object-Oriented (UML)** murni. Di dalam notasi UML resmi, **tidak terdapat diagram ERD terpisah**, melainkan struktur data sistem dimodelkan melalui **UML Class Diagram** (Chapter 1, Subbab 1.1.7, hlm. 17–19, Tabel 1.8).

Namun, dalam arsitektur perangkat lunak berbasis web modern dengan pola **Object-Relational Mapping (ORM)** (seperti framework Laravel pada aplikasi Tracer Study ini), arsitektur data memiliki dua representasi yang saling berkorespondensi:
1. **Model Relasional Fisik (ERD)**: Memodelkan skema tabel, kolom, tipe data SQL, batasan integritas kunci (`PK`, `FK`, `UK`), dan kardinalitas relasi fisik pada database MySQL `tracer`.
2. **Model Domain Berorientasi Objek (UML Class Diagram)**: Memodelkan kelas entitas aplikasi (`User`, `Biodata`, `Kuesioner`, `EvaluasiAtasan`) beserta metode relasinya (`hasMany`, `belongsTo`, `belongsToMany`).

### 3.2 Pemetaan Formal ERD Relasional ke UML Class Diagram (Sundaramoorthy 2022)

| Konsep ERD Relasional | Padanan UML Class Diagram (Sundaramoorthy 2022, hlm. 17–19) | Implementasi Nyata pada Aplikasi Tracer Study UKDW |
| :--- | :--- | :--- |
| **Entitas Relasional (Tabel)** | **Class** (Nama kelas, atribut, metode) | Model Eloquent Laravel (misal: `class Biodata extends Model`) |
| **Kolom Data / Field** | **Attributes** (`visibility name : type`) | Properti tabel dan atribut `$fillable` / `$casts` |
| **Primary Key (`PK`)** | Identitas Unik Objek (*Object Identifier*) | Kolom `id` atau kode pengenal unik (misal: `kode_provinsi`) |
| **Foreign Key (`FK`)** | **Directed Association** dengan relasi navigasi | Definisi relasi ORM `belongsTo()` dan `hasMany()` |
| **Kardinalitas ($1:1, 1:N, N:M$)** | **Multiplicity** (`1`, `0..1`, `1..*`, `*`) | Multiplisitas relasi antarkelas pada level kode |
| **Cascade Delete (Ketergantungan Kuat)** | **Composition** (Wajik penuh $\blacklozenge$, Sundaramoorthy hlm. 19) | Hubungan kepemilikan mutlak: bila kuesioner dihapus, section dan pertanyaan ikut terhapus |
| **Non-identifying FK (Ketergantungan Lemah)** | **Aggregation** (Wajik kosong $\diamond$, Sundaramoorthy hlm. 18) | Hubungan keanggotaan bebas: alumni merujuk `perusahaan`; penghapusan data alumni tidak menghapus master perusahaan |

### 3.3 Kaidah Normalisasi dan Integritas Relasional
1. **Normalisasi Data**:
   - **1NF**: Setiap kolom berisi nilai atomik dan tidak terdapat kelompok atribut yang berulang (*repeating groups*).
   - **2NF**: Seluruh atribut non-kunci bergantung secara fungsional penuh pada primary key (*no partial dependencies*).
   - **3NF**: Mengeliminasi ketergantungan transitif (*no transitive dependencies*). Pada sistem Tracer Study UKDW, hal ini diwujudkan dengan memisahkan master wilayah ke tabel `propinsi` dan `kabupaten` berkode resmi BPS/Kemendagri.
2. **Integritas Referensial (*Referential Integrity*)**:
   - Menerapkan batasan `ON DELETE RESTRICT` pada tabel referensi pokok (seperti data akademik, fakultas, prodi, dan wilayah) guna menjaga keabsahan data historis audit pelacakan jejak alumni.
   - Menerapkan batasan `ON DELETE CASCADE` secara tepat hanya pada data transaksi dependen yang tidak memiliki makna mandiri tanpa entitas induknya (misal: opsi jawaban terhadap pertanyaan kuesioner).

