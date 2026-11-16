import { jsPDF } from 'jspdf';
import autoTable from 'jspdf-autotable';

/**
 * Utilitas untuk menghasilkan berkas laporan PDF analitik Tracer Study UKDW.
 * Format dokumen menyatukan judul indikator, metadata filter, ringkasan analisis,
 * visualisasi grafik, serta tabel data pembentuk dalam satu tata letak rapi tanpa dialog cetak browser.
 *
 * @param {Object} options
 * @param {string} options.title - Judul kategori indikator (misal: "01. Tingkat Respons Alumni (Response Rate)")
 * @param {string} [options.subtitle] - Deskripsi singkat indikator
 * @param {string|number} [options.cohortYear] - Tahun kohort lulusan
 * @param {string} [options.scopeName] - Nama prodi/fakultas/cakupan
 * @param {string} [options.summaryText] - Teks narasi analisis capaian
 * @param {string} [options.chartImageBase64] - Gambar grafik berformat base64 PNG dari ECharts
 * @param {Array<string>} [options.tableHeaders] - Header kolom tabel data
 * @param {Array<Array<any>>} [options.tableRows] - Baris isi tabel data
 * @param {string} [options.fileName] - Nama berkas unduhan
 */
export function generateAnalyticsPdf({
    title = 'Laporan Analisis Tracer Study',
    subtitle = '',
    cohortYear = '',
    scopeName = '',
    summaryText = '',
    chartImageBase64 = null,
    tableHeaders = [],
    tableRows = [],
    fileName = 'Laporan_Analisis_Tracer_Study.pdf',
}) {
    // Gunakan orientasi landscape agar visualisasi grafik dan tabel banyak kolom muat secara leluasa dan rapi
    const doc = new jsPDF({
        orientation: 'landscape',
        unit: 'mm',
        format: 'a4',
    });

    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();
    const margin = 14;
    const contentWidth = pageWidth - (margin * 2);

    let currentY = margin;

    // 1. Header Laporan Formal & Ringkas
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(14);
    doc.setTextColor(13, 84, 43); // Hijau UKDW #0D542B
    doc.text('LAPORAN ANALISIS TRACER STUDY', margin, currentY);

    currentY += 6;
    doc.setFontSize(11);
    doc.setTextColor(30, 41, 59); // Slate gelap #1E293B
    doc.text(title, margin, currentY);

    currentY += 5;
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(8.5);
    doc.setTextColor(100, 116, 139); // Slate netral #64748B

    const now = new Date();
    const formattedDate = `${now.getDate().toString().padStart(2, '0')}/${(now.getMonth() + 1).toString().padStart(2, '0')}/${now.getFullYear()} ${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')} WIB`;

    const metaParts = [];
    if (cohortYear) {
        metaParts.push(`Tahun Kelulusan: ${cohortYear}`);
    }
    if (scopeName) {
        metaParts.push(`Cakupan: ${scopeName}`);
    }
    metaParts.push(`Waktu Penarikan Data: ${formattedDate}`);

    doc.text(metaParts.join('   |   '), margin, currentY);

    // Garis pemisah atas
    currentY += 3;
    doc.setDrawColor(203, 213, 225); // #CBD5E1
    doc.setLineWidth(0.4);
    doc.line(margin, currentY, pageWidth - margin, currentY);
    currentY += 5;

    // 2. Ringkasan Narasi Capaian / Kilas Indikator (Jika ada)
    if (summaryText && summaryText.trim()) {
        doc.setFillColor(248, 250, 252); // #F8FAFC
        doc.setDrawColor(226, 232, 240); // #E2E8F0
        doc.setLineWidth(0.3);

        const cleanSummary = summaryText.replace(/\s+/g, ' ').trim();
        const splitSummary = doc.splitTextToSize(cleanSummary, contentWidth - 8);
        const boxHeight = (splitSummary.length * 4) + 6;

        doc.roundedRect(margin, currentY, contentWidth, boxHeight, 2, 2, 'FD');

        doc.setFont('helvetica', 'bold');
        doc.setFontSize(8);
        doc.setTextColor(13, 84, 43);
        doc.text('RINGKASAN ANALISIS CAPAIAN', margin + 4, currentY + 4.5);

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(8);
        doc.setTextColor(51, 65, 85);
        doc.text(splitSummary, margin + 4, currentY + 8.5);

        currentY += boxHeight + 5;
    }

    // 3. Visualisasi Grafik (Jika ada gambar grafik base64)
    if (chartImageBase64) {
        // Alokasikan tinggi proporsional agar grafik tampil jelas dan tajam
        const chartWidth = contentWidth;
        const chartHeight = 85;

        // Cek jika ruang sisa di halaman 1 tidak mencukupi, pindah ke halaman baru
        if (currentY + chartHeight > pageHeight - 20) {
            doc.addPage();
            currentY = margin;
        }

        try {
            doc.addImage(chartImageBase64, 'PNG', margin, currentY, chartWidth, chartHeight);
            currentY += chartHeight + 6;
        } catch (e) {
            console.error('Gagal menyisipkan gambar grafik ke PDF:', e);
        }
    }

    // 4. Tabel Lengkap Seluruh Data Pembentuk
    if (tableHeaders.length > 0 && tableRows.length > 0) {
        // Cek apakah tabel sebaiknya mulai di halaman berikutnya agar rapi
        if (currentY > pageHeight - 50) {
            doc.addPage();
            currentY = margin;
        }

        autoTable(doc, {
            startY: currentY,
            head: [tableHeaders],
            body: tableRows,
            theme: 'grid',
            headStyles: {
                fillColor: [13, 84, 43], // Hijau UKDW #0D542B
                textColor: [255, 255, 255],
                fontStyle: 'bold',
                fontSize: 8,
                halign: 'center',
                valign: 'middle',
            },
            bodyStyles: {
                fontSize: 8,
                textColor: [30, 41, 59],
                valign: 'middle',
            },
            alternateRowStyles: {
                fillColor: [248, 250, 252],
            },
            tableLineColor: [226, 232, 240],
            tableLineWidth: 0.2,
            margin: { left: margin, right: margin, bottom: 16 },
            didDrawPage: (data) => {
                // Footer penomoran halaman dan validitas dokumen di setiap halaman
                const totalPages = doc.internal.getNumberOfPages();
                const pageNum = data.pageNumber;

                doc.setFont('helvetica', 'normal');
                doc.setFontSize(7.5);
                doc.setTextColor(148, 163, 184);

                doc.text(
                    'Tracer Study - Universitas Kristen Duta Wacana',
                    margin,
                    pageHeight - 8
                );

                const pageStr = `Halaman ${pageNum} dari ${totalPages}`;
                const pageStrWidth = doc.getStringUnitWidth(pageStr) * 7.5 / doc.internal.scaleFactor;
                doc.text(pageStr, pageWidth - margin - pageStrWidth, pageHeight - 8);
            },
        });
    }

    // Unduh langsung berkas PDF ke perangkat pengguna
    const cleanFileName = fileName.endsWith('.pdf') ? fileName : `${fileName}.pdf`;
    doc.save(cleanFileName);
}
