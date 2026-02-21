import express from "express";
import cors from "cors";
import Replicate from "replicate";
import dotenv from "dotenv";
import rateLimit from "express-rate-limit";

dotenv.config();

const app = express();

// 🔒 Basic Security + Limits
app.use(cors());
app.use(express.json({ limit: "50mb" }));

// 🚫 Rate limiting (prevents abuse - 20 requests per 15 mins)
const limiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  max: 20,
  message: { success: false, error: "Too many forges! Please wait 15 minutes." }
});
app.use(limiter);

// 🔐 Secret key protection from Render Env Variables
const FORGE_SECRET = process.env.FORGE_SECRET;

// 🧠 Product Dimension Map (InkedJoy Catalog)
const PRODUCT_CATALOG = {
  "SKU-17193": { name: "MERCH BEANIE", w: 1200, h: 1200 },
  "SKU-17192": { name: "MERCH SOCKS", w: 562, h: 2244 },
  "SKU-11009": { name: "MERCH HOODIE", w: 2500, h: 2500 },
  "SKU-8043": { name: "VANILLA VICE", w: 1200, h: 1200 },
  "SKU-7995": { name: "MERCH MOUSEPAD", w: 1635, h: 1783 },
  "SKU-7992": { name: "MERCH TUMBLER", w: 2400, h: 2100 },
  "SKU-7988": { name: "MERCH TOTE BAG", w: 2392, h: 2528 },
  "SKU-7977": { name: "SHOULDER BAG", w: 2800, h: 2800 },
  "SKU-7976": { name: "MERCH POSTER", w: 3600, h: 5400 },
  "SKU-7965": { name: "MERCH CANVAS", w: 4800, h: 4800 },
  "SKU-7960": { name: "MERCH MUG", w: 2475, h: 1155 },
  "SKU-13013": { name: "CASUAL CHEST BAG", w: 3056, h: 2740 },
  "SKU-13007": { name: "TRAVEL MUG (20oz)", w: 2800, h: 2400 },
  "SKU-12989": { name: "WHITE MUG (NEW)", w: 2475, h: 1155 },
  "SKU-JAR-15": { name: "15oz SOY CANDLE", w: 1800, h: 1200 },
  "SKU-JAR-09": { name: "9oz SOY CANDLE", w: 1500, h: 1000 }
};

// 🤖 Replicate AI Connection
const replicate = new Replicate({
  auth: process.env.REPLICATE_API_TOKEN
});

// 🟢 Status Check Route (Browser Test)
app.get("/", (req, res) => {
  res.send('<body style="background:#000;color:#0f0;text-align:center;padding:50px;font-family:monospace;"><h1>🏺 SENSI FORGE: ACTIVE</h1></body>');
});

// 🔥 Main Forge Endpoint
app.post("/forge-merch", async (req, res) => {
  const clientSecret = req.headers["x-forge-secret"];

  // Check if the key in the header matches your FORGE_SECRET variable
  if (!clientSecret || clientSecret !== FORGE_SECRET) {
    return res.status(403).json({
      success: false,
      error: "Unauthorized Forge Access"
    });
  }

  const { userImage, selectedSku, tagline } = req.body;
  const product = PRODUCT_CATALOG[selectedSku] || PRODUCT_CATALOG["SKU-11009"];

  try {
    console.log(`🚀 Forging ${product.name} at ${product.w}x${product.h}`);

    const output = await replicate.run(
      "tencentarc/instant-id-multicontrolnet:35324a7df2397e6e57dfd8f4f9d2910425f5123109c8c3ed035e769aeff9ff3c",
      {
        input: {
          face_image: userImage,
          prompt: `Professional ${product.name} design featuring text "${tagline}", cinematic lighting, masterpiece, ultra high resolution`,
          width: product.w,
          height: product.h,
          negative_prompt: "low quality, blurry, distorted face, messy text"
        }
      }
    );

    res.json({
      success: true,
      imageUrl: output[0],
      sku: selectedSku
    });

  } catch (err) {
    console.error(err);
    res.status(500).json({
      success: false,
      error: "The Forge encountered a critical failure."
    });
  }
});

app.listen(process.env.PORT || 3000, () => {
  console.log("🔥 Sensi Forge running...");
});
