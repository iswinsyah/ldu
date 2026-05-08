/**
 * Nama file: Code.gs
 * Ini adalah pengganti server.js, berjalan sepenuhnya di server Google.
 */

/**
 * Fungsi utama yang berjalan ketika Web App menerima request POST dari landing page.
 * @param {Object} e - Event object yang berisi data dari request.
 */
function doPost(e) {
  try {
    Logger.log("✅ Menerima data lead baru via GAS...");

    // 1. Ambil API Keys secara aman dari Script Properties
    const scriptProperties = PropertiesService.getScriptProperties();
    const GEMINI_API_KEY = scriptProperties.getProperty('GEMINI_API_KEY');
    const FONNTE_API_KEY = scriptProperties.getProperty('FONNTE_API_KEY');

    // 2. Ambil data yang dikirim oleh form di landingpage1.html
    const { nama, whatsapp, harapan_user } = JSON.parse(e.postData.contents);

    // Validasi data dasar
    if (!nama || !whatsapp || !harapan_user) {
      return createJsonResponse({ message: "Data tidak lengkap." });
    }

    // =================================================================
    // AI AGENT 2: ANALIS DATA (Menentukan Buyer Persona)
    // =================================================================
    Logger.log("🧠 AI Agent 2 (Analis) mulai bekerja...");
    const promptAnalis = `Sebagai seorang analis marketing spiritual, analisis data calon donatur ini:
    - Nama: ${nama}
    - Harapan Terbesar: "${harapan_user}"
    Tentukan Buyer Persona-nya dalam 3 kata kunci (contoh: Pekerja, Beban Finansial, Butuh Ketenangan).`;
    
    const buyerPersona = callGeminiAPI(promptAnalis, GEMINI_API_KEY);
    Logger.log(`-> Persona terdeteksi: ${buyerPersona}`);

    // =================================================================
    // AI AGENT 1: CS (Follow Up via Fonnte)
    // =================================================================
    Logger.log("📱 AI Agent 1 (CS) mulai bekerja...");
    const promptCS = `Anda adalah CS dari program sedekah Villa Quran. Buatlah pesan WhatsApp yang sangat ramah dan personal untuk ${nama} yang memiliki persona (${buyerPersona}).
    Isi pesan:
    1. Sapa dengan namanya.
    2. Ucapkan terima kasih telah tertarik dengan ebook "7 Kebiasaan Pembuka Rejeki ala Nabi".
    3. Berikan link download ebook ini: https://link-ebook-bos.com/download
    4. Ajak dia untuk download aplikasi "Panduan Hidup Berkah" sebagai pelengkap.
    Gunakan bahasa yang menyentuh dan sesuai dengan harapannya.`;

    const pesanWA = callGeminiAPI(promptCS, GEMINI_API_KEY);
    sendFonnteMessage(pesanWA, whatsapp, FONNTE_API_KEY);
    Logger.log(`-> Pesan WA terkirim ke ${whatsapp}`);

    // =================================================================
    // AI AGENT 3: CONTENT CALENDAR (Membuat Ide Konten)
    // =================================================================
    Logger.log("📅 AI Agent 3 (Content Calendar) mulai bekerja...");
    const promptKonten = `Berdasarkan persona donatur (${buyerPersona}), buatkan 1 ide konten video TikTok berdurasi 15 detik yang sangat relevan untuk menarik lebih banyak orang seperti dia.
    Format output harus berupa JSON dengan struktur: {"title": "Judul Video", "script": "Naskah voice over singkat", "visual_idea": "Ide visual per adegan"}`;

    const ideKontenRaw = callGeminiAPI(promptKonten, GEMINI_API_KEY);
    const ideKonten = JSON.parse(ideKontenRaw.replace(/```json|```/g, ''));
    Logger.log(`-> Ide konten baru dibuat: ${ideKonten.title}`);

    // TODO: Simpan 'ideKonten' ini ke dalam Google Sheets
    // SpreadsheetApp.openById('SHEET_ID').getSheetByName('Ide Konten').appendRow([new Date(), ideKonten.title, ideKonten.script, ideKonten.visual_idea]);

    // Kirim respon sukses kembali ke landing page
    return createJsonResponse({ message: "Proses funneling berhasil dijalankan!" });

  } catch (error) {
    Logger.log(`❌ Terjadi kesalahan pada orkestrasi: ${error.toString()}`);
    return createJsonResponse({ message: "Terjadi kesalahan di server.", error: error.toString() });
  }
}

// Helper function untuk memanggil Gemini REST API menggunakan UrlFetchApp
function callGeminiAPI(prompt, apiKey) {
  const url = `https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=${apiKey}`;
  const payload = { "contents": [{ "parts": [{ "text": prompt }] }] };
  const options = {
    'method': 'post',
    'contentType': 'application/json',
    'payload': JSON.stringify(payload),
    'muteHttpExceptions': true
  };
  const response = UrlFetchApp.fetch(url, options);
  const data = JSON.parse(response.getContentText());
  return data.candidates[0].content.parts[0].text;
}

// Helper function untuk mengirim pesan via Fonnte
function sendFonnteMessage(message, target, token) {
    const url = 'https://api.fonnte.com/send';
    const payload = { 'target': target, 'message': message };
    const options = {
        'method': 'post',
        'contentType': 'application/json',
        'headers': { 'Authorization': token },
        'payload': JSON.stringify(payload)
    };
    UrlFetchApp.fetch(url, options);
}

// Helper function untuk membuat response JSON standar untuk Web App
function createJsonResponse(data) {
  return ContentService
    .createTextOutput(JSON.stringify(data))
    .setMimeType(ContentService.MimeType.JSON);
}