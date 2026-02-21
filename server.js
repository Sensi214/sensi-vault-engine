import express from "express";
import cors from "cors";
import Replicate from "replicate";
import dotenv from "dotenv";
import rateLimit from "express-rate-limit";

dotenv.config();

const app = express();
app.use(cors());
app.use(express.json({ limit: "50mb" }));

// 🛡️ THE SHIELD: Prevents anyone from spamming your AI
const limiter = rateLimit({
  windowMs: 15 * 60 * 1000, // 15 minutes
  max: 20, // Limit each user to 20 forges per window
  message: { success: false, error: "Too many requests. Please wait 15 minutes." }
});
app.use(limiter);

// 🧠 THE CATALOG: Dimensions for all 16 InkedJoy products
const PRODUCT_CATALOG = {
  "SKU-17193": { name: "MERCH BEANIE", w: 1200, h: 1200 },
  "SKU-17192": { name: "MERCH SOCKS", w: 562, h: 2244 },
  "SKU-11009": { name: "MERCH HOODIE", w: 2500, h: 2500 },
  "SKU-8043":  { name: "VANILLA VICE", w: 1200, h: 1200 },
  "SKU-7995":  { name: "MERCH MOUSEPAD", w: 1635, h: 1783 },
  "SKU-7992":  { name: "MERCH TUMBLER", w: 2400, h: 2100 },
  "SKU-7988":  { name: "MERCH TOTE BAG", w: 2392, h: 2528 },
  "SKU-7977":  { name: "SHOULDER BAG", w: 2800, h: 2800 },
  "SKU-7976":  { name: "MERCH POSTER", w: 3600, h: 5400 },
  "SKU-7965":  { name: "MERCH CANVAS", w: 4800, h: 4800 },
  "SKU-7960":  { name: "MERCH MUG", w: 2475, h: 1155 },
  "SKU-13013": { name: "CASUAL CHEST BAG", w: 3056, h: 2740 },
  "SKU-13007": { name: "TRAVEL MUG (20oz)", w: 2800, h: 2400 },
  "SKU-12989": { name: "WHITE MUG (NEW)", w: 2475, h: 1155 },
  "SKU-JAR-15": { name: "15oz SOY CANDLE", w: 1800, h: 1200 },
  "SKU-JAR-09": { name: "9oz SOY CANDLE", w: 1500, h: 1000 }
};

const replicate = new Replicate({ auth: process.env.REPLICATE_API_TOKEN });

app.get("/", (req, res) => res.send("🏺 SENSI FORGE: ACTIVE"));

app.post("/forge-merch", async (req, res) => {
  const clientSecret = req.headers["x-forge-secret"];
  
  // Security Handshake
  if (clientSecret !== process.env.FORGE_SECRET) {
    return res.status(403).json({ success: false, error: "Unauthorized" });
  }

  const { userImage, selectedSku, tagline } = req.body;
  const product = PRODUCT_CATALOG[selectedSku] || PRODUCT_CATALOG["SKU-11009"];

  try {
    const output = await replicate.run(
      "tencentarc/instant-id-multicontrolnet:35324a7df2397e6e57dfd8f4f9d2910425f5123109c8c3ed035e769aeff9ff3c",
      {
        input: {
          face_image: userImage,
          prompt: `A professional ${product.name} design, text: ${tagline}, high quality`,
          width: product.w,
          height: product.h
        }
      }
    );
    res.json({ success: true, imageUrl: output[0] });
  } catch (err) {
    res.status(500).json({ success: false, error: "Forge failed." });
  }
});

app.listen(process.env.PORT || 3000);
