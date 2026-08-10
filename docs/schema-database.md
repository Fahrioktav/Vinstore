# Prisma Schema Vinstore

Berikut schema database Vinstore dalam format Prisma-style, disusun dari migration yang ada di repository.

```prisma
generator client {
    provider = "prisma-client-js"
}

datasource db {
    provider = "mysql"
    url      = env("DATABASE_URL")
}

enum AuctionApprovalStatus {
    pending_validator
    pending_admin
    approved
    rejected
}

enum AuctionStatus {
    pending
    scheduled
    active
    ended
    cancelled
}

enum RequestStatus {
    pending
    approved
    rejected
}

enum ContactStatus {
    pending
    replied
    closed
}

model User {
    id                    BigInt    @id @default(autoincrement())
    publicId              String?   @unique @map("public_id") @db.VarChar(10)
    username              String    @unique
    firstName             String    @map("first_name")
    lastName              String    @map("last_name")
    email                 String    @unique
    googleId              String?   @unique @map("google_id")
    phone                 String    @unique
    address               String
    photo                 String?
    password              String?
    rememberToken         String?   @map("remember_token")
    role                  String    @default("user")
    createdAt             DateTime? @map("created_at")
    updatedAt             DateTime? @map("updated_at")

    store                 Store?
    carts                 Cart[]
    orders                Order[]
    auctionBids           AuctionBid[]
    wonAuctions           Auction[]          @relation("AuctionWinner")
    approvedProducts      Product[]          @relation("ProductApprovedBy")
    validatedProducts     Product[]          @relation("ProductValidatedBy")
    approvedAuctions      Auction[]          @relation("AuctionApprovedBy")
    validatedAuctions     Auction[]          @relation("AuctionValidatedBy")
    refundRequests        RefundRequest[]
    reviewedRefunds       RefundRequest[]    @relation("RefundReviewedBy")
    withdrawalRequests    WithdrawalRequest[]
    reviewedWithdrawals   WithdrawalRequest[] @relation("WithdrawalReviewedBy")
    supportMessagesSent   SupportMessage[]   @relation("SupportMessageSender")
    supportThreads        SupportMessage[]   @relation("SupportMessageThreadOwner")
    contacts              Contact[]
    conversations         Conversation[]     @relation("ConversationBuyer")
    messages              Message[]
    priceGuesses          PriceGuess[]
    guessedProducts       Product[]          @relation("ProductGuessWinner")

    @@map("users")
}

model Store {
    id                   BigInt    @id @default(autoincrement())
    publicId             String?   @unique @map("public_id") @db.VarChar(11)
    userId               BigInt    @map("user_id")
    storeName            String    @map("store_name")
    category             String
    description          String
    location             String
    photo                String?
    latitude             Decimal?  @db.Decimal(10, 7)
    longitude            Decimal?  @db.Decimal(10, 7)
    availableBalance     Decimal   @default(0) @map("available_balance") @db.Decimal(14, 2)
    withdrawnBalance     Decimal   @default(0) @map("withdrawn_balance") @db.Decimal(14, 2)
    createdAt            DateTime? @map("created_at")
    updatedAt            DateTime? @map("updated_at")

    user                 User      @relation(fields: [userId], references: [id], onDelete: Cascade)
    products             Product[]
    auctions             Auction[]
    orders               Order[]
    withdrawalRequests   WithdrawalRequest[]
    conversations        Conversation[]
    requesterTradeIns     TradeInRequest[] @relation("TradeInRequesterStore")
    responderTradeIns     TradeInRequest[] @relation("TradeInResponderStore")

    @@map("stores")
}

model Category {
    id         BigInt    @id @default(autoincrement())
    publicId   String?   @unique @map("public_id") @db.VarChar(11)
    name       String    @unique @db.VarChar(100)
    image      String?
    createdAt  DateTime? @map("created_at")
    updatedAt  DateTime? @map("updated_at")

    @@map("categories")
}

model Product {
    id                  BigInt    @id @default(autoincrement())
    publicId            String?   @unique @map("public_id") @db.VarChar(11)
    storeId             BigInt    @map("store_id")
    name                String
    stock               Int       @default(0)
    price               Decimal   @db.Decimal(12, 2)
    category            String
    description         String
    image               String?
    images              Json?
    video               String?
    certificate         String?
    isTradeInEnabled        Boolean   @default(false) @map("is_trade_in_enabled")
    approvalStatus      String    @default("pending") @map("approval_status")
    approvedAt          DateTime? @map("approved_at")
    approvedBy          BigInt?   @map("approved_by")
    rejectionReason     String?   @map("rejection_reason")
    validatedAt         DateTime? @map("validated_at")
    validatedBy         BigInt?   @map("validated_by")
    saleType            String    @default("normal") @map("sale_type")
    guessStartsAt       DateTime? @map("guess_starts_at")
    guessEndsAt         DateTime? @map("guess_ends_at")
    guessStatus         String?
    guessWinnerId       BigInt?   @map("guess_winner_id")
    guessWinningAmount  Decimal?  @map("guess_winning_amount") @db.Decimal(12, 2)
    guessFinishedAt     DateTime? @map("guess_finished_at")
    winnerPriorityUntil DateTime? @map("winner_priority_until")
    createdAt           DateTime? @map("created_at")
    updatedAt           DateTime? @map("updated_at")

    store               Store     @relation(fields: [storeId], references: [id], onDelete: Cascade)
    approver            User?     @relation("ProductApprovedBy", fields: [approvedBy], references: [id], onDelete: SetNull)
    validator           User?     @relation("ProductValidatedBy", fields: [validatedBy], references: [id], onDelete: SetNull)
    guessWinner         User?     @relation("ProductGuessWinner", fields: [guessWinnerId], references: [id], onDelete: SetNull)
    carts               Cart[]
    orders              Order[]
    tradeInOffered       TradeInRequest[] @relation("TradeInOfferedProduct")
    tradeInRequested     TradeInRequest[] @relation("TradeInRequestedProduct")
    priceGuesses        PriceGuess[]

    @@map("products")
}

model Cart {
    id         BigInt    @id @default(autoincrement())
    publicId   String?   @unique @map("public_id") @db.VarChar(11)
    userId     BigInt    @map("user_id")
    productId  BigInt    @map("product_id")
    quantity   Int       @default(1)
    createdAt  DateTime? @map("created_at")
    updatedAt  DateTime? @map("updated_at")

    user       User      @relation(fields: [userId], references: [id], onDelete: Cascade)
    product    Product   @relation(fields: [productId], references: [id], onDelete: Cascade)

    @@map("carts")
}

model Order {
    id                    BigInt    @id @default(autoincrement())
    publicId              String?   @unique @map("public_id") @db.VarChar(11)
    userId                BigInt    @map("user_id")
    productId             BigInt?   @map("product_id")
    auctionId             BigInt?   @map("auction_id")
    storeId               BigInt    @map("store_id")
    quantity              Int       @default(1)
    price                 Decimal   @db.Decimal(12, 2)
    status                String    @default("Waiting")
    trackingNumber        String?   @map("tracking_number")
    paymentReference      String?   @map("payment_reference")
    paymentStatus         String    @default("unpaid") @map("payment_status")
    paymentMethod         String?   @map("payment_method")
    midtransTransactionId String?   @map("midtrans_transaction_id")
    snapToken             String?   @map("snap_token")
    snapRedirectUrl       String?   @map("snap_redirect_url")
    paidAt                DateTime? @map("paid_at")
    stockRestoredAt       DateTime? @map("stock_restored_at")
    sellerReleasedAt      DateTime? @map("seller_released_at")
    createdAt             DateTime? @map("created_at")
    updatedAt             DateTime? @map("updated_at")

    user                  User      @relation(fields: [userId], references: [id], onDelete: Cascade)
    product               Product?  @relation(fields: [productId], references: [id], onDelete: Cascade)
    auction               Auction?  @relation(fields: [auctionId], references: [id], onDelete: SetNull)
    store                 Store     @relation(fields: [storeId], references: [id], onDelete: Cascade)
    refundRequests        RefundRequest[]

    @@map("orders")
}

model Auction {
    id              BigInt    @id @default(autoincrement())
    publicId        String    @unique @map("public_id")
    storeId         BigInt    @map("store_id")
    winnerId        BigInt?   @map("winner_id")
    name            String
    description     String
    image           String?
    startingPrice   Decimal   @map("starting_price") @db.Decimal(12, 2)
    minIncrement    Decimal   @map("min_increment") @db.Decimal(12, 2)
    currentPrice    Decimal?  @map("current_price") @db.Decimal(12, 2)
    bidsCount       Int       @default(0) @map("bids_count")
    approvalStatus  AuctionApprovalStatus @default(pending_validator) @map("approval_status")
    status          AuctionStatus         @default(pending)
    startsAt        DateTime  @map("starts_at")
    endsAt          DateTime  @map("ends_at")
    approvedAt      DateTime? @map("approved_at")
    approvedBy      BigInt?   @map("approved_by")
    validatedAt     DateTime? @map("validated_at")
    validatedBy     BigInt?   @map("validated_by")
    rejectionReason String?   @map("rejection_reason")
    endedAt         DateTime? @map("ended_at")
    createdAt       DateTime? @map("created_at")
    updatedAt       DateTime? @map("updated_at")

    store           Store     @relation(fields: [storeId], references: [id], onDelete: Cascade)
    winner          User?     @relation("AuctionWinner", fields: [winnerId], references: [id], onDelete: SetNull)
    approver        User?     @relation("AuctionApprovedBy", fields: [approvedBy], references: [id], onDelete: SetNull)
    validator       User?     @relation("AuctionValidatedBy", fields: [validatedBy], references: [id], onDelete: SetNull)
    bids            AuctionBid[]
    orders          Order[]

    @@map("auctions")
}

model AuctionBid {
    id         BigInt    @id @default(autoincrement())
    auctionId  BigInt    @map("auction_id")
    userId     BigInt    @map("user_id")
    amount     Decimal   @db.Decimal(12, 2)
    createdAt  DateTime? @map("created_at")
    updatedAt  DateTime? @map("updated_at")

    auction    Auction   @relation(fields: [auctionId], references: [id], onDelete: Cascade)
    user       User      @relation(fields: [userId], references: [id], onDelete: Cascade)

    @@index([auctionId, amount])
    @@map("auction_bids")
}

model TradeInRequest {
    id                   BigInt    @id @default(autoincrement())
    publicId             String    @unique @map("public_id")
    requesterStoreId     BigInt    @map("requester_store_id")
    responderStoreId     BigInt    @map("responder_store_id")
    offeredProductId     BigInt    @map("offered_product_id")
    requestedProductId   BigInt    @map("requested_product_id")
    additionalCash       Decimal   @default(0) @map("additional_cash") @db.Decimal(12, 2)
    note                 String?
    status               RequestStatus @default(pending)
    respondedAt          DateTime? @map("responded_at")
    paymentStatus        PaymentStatus @default(not_required) @map("payment_status")
    paymentReference     String?   @map("payment_reference")
    snapToken            String?   @map("snap_token")
    midtransTransactionId String?  @map("midtrans_transaction_id")
    paidAt               DateTime? @map("paid_at")
    createdAt            DateTime? @map("created_at")
    updatedAt            DateTime? @map("updated_at")

    requesterStore       Store     @relation("TradeInRequesterStore", fields: [requesterStoreId], references: [id], onDelete: Cascade)
    responderStore       Store     @relation("TradeInResponderStore", fields: [responderStoreId], references: [id], onDelete: Cascade)
    offeredProduct       Product   @relation("TradeInOfferedProduct", fields: [offeredProductId], references: [id], onDelete: Cascade)
    requestedProduct     Product   @relation("TradeInRequestedProduct", fields: [requestedProductId], references: [id], onDelete: Cascade)

    @@map("trade_in_requests")
}

model PriceGuess {
    id         BigInt    @id @default(autoincrement())
    publicId   String    @unique @map("public_id")
    productId  BigInt    @map("product_id")
    userId     BigInt    @map("user_id")
    amount     Decimal   @db.Decimal(12, 2)
    createdAt  DateTime? @map("created_at")
    updatedAt  DateTime? @map("updated_at")

    product    Product   @relation(fields: [productId], references: [id], onDelete: Cascade)
    user       User      @relation(fields: [userId], references: [id], onDelete: Cascade)

    @@unique([productId, userId])
    @@map("price_guesses")
}

model RefundRequest {
    id           BigInt    @id @default(autoincrement())
    publicId     String    @unique @map("public_id")
    orderId      BigInt    @map("order_id")
    userId       BigInt    @map("user_id")
    reason       String
    proofImage   String?   @map("proof_image")
    status       RequestStatus @default(pending)
    adminNote    String?   @map("admin_note")
    reviewedBy   BigInt?   @map("reviewed_by")
    reviewedAt   DateTime? @map("reviewed_at")
    createdAt    DateTime? @map("created_at")
    updatedAt    DateTime? @map("updated_at")

    order        Order     @relation(fields: [orderId], references: [id], onDelete: Cascade)
    user         User      @relation(fields: [userId], references: [id], onDelete: Cascade)
    reviewer     User?     @relation("RefundReviewedBy", fields: [reviewedBy], references: [id], onDelete: SetNull)

    @@map("refund_requests")
}

model WithdrawalRequest {
    id           BigInt    @id @default(autoincrement())
    publicId     String    @unique @map("public_id")
    storeId      BigInt    @map("store_id")
    amount       Decimal   @db.Decimal(14, 2)
    bankName     String    @map("bank_name")
    accountNumber String   @map("account_number")
    accountHolder String    @map("account_holder")
    status       RequestStatus @default(pending)
    adminNote    String?   @map("admin_note")
    reviewedBy   BigInt?   @map("reviewed_by")
    reviewedAt   DateTime? @map("reviewed_at")
    createdAt    DateTime? @map("created_at")
    updatedAt    DateTime? @map("updated_at")

    store        Store     @relation(fields: [storeId], references: [id], onDelete: Cascade)
    reviewer     User?     @relation("WithdrawalReviewedBy", fields: [reviewedBy], references: [id], onDelete: SetNull)

    @@map("withdrawal_requests")
}

model Conversation {
    id             BigInt    @id @default(autoincrement())
    publicId       String    @unique @map("public_id")
    storeId        BigInt    @map("store_id")
    buyerId        BigInt    @map("buyer_id")
    lastMessageId  BigInt?   @map("last_message_id")
    lastMessageAt  DateTime? @map("last_message_at")
    createdAt      DateTime? @map("created_at")
    updatedAt      DateTime? @map("updated_at")

    store          Store     @relation(fields: [storeId], references: [id], onDelete: Cascade)
    buyer          User      @relation("ConversationBuyer", fields: [buyerId], references: [id], onDelete: Cascade)
    messages       Message[]

    @@unique([storeId, buyerId])
    @@map("conversations")
}

model Message {
    id             BigInt    @id @default(autoincrement())
    conversationId BigInt    @map("conversation_id")
    senderId       BigInt    @map("sender_id")
    body           String
    readAt         DateTime? @map("read_at")
    createdAt      DateTime? @map("created_at")
    updatedAt      DateTime? @map("updated_at")

    conversation   Conversation @relation(fields: [conversationId], references: [id], onDelete: Cascade)
    sender         User         @relation(fields: [senderId], references: [id], onDelete: Cascade)

    @@map("messages")
}

model SupportMessage {
    id         BigInt    @id @default(autoincrement())
    userId     BigInt    @map("user_id")
    senderId   BigInt    @map("sender_id")
    body       String
    readAt     DateTime? @map("read_at")
    createdAt  DateTime? @map("created_at")
    updatedAt  DateTime? @map("updated_at")

    threadOwner User     @relation("SupportMessageThreadOwner", fields: [userId], references: [id], onDelete: Cascade)
    sender      User     @relation("SupportMessageSender", fields: [senderId], references: [id], onDelete: Cascade)

    @@index([userId, createdAt])
    @@map("support_messages")
}

model Contact {
    id         BigInt    @id @default(autoincrement())
    publicId   String?   @unique @map("public_id") @db.VarChar(11)
    userId     BigInt?   @map("user_id")
    name       String
    email      String
    subject    String
    message    String
    status     ContactStatus @default(pending)
    adminReply String?   @map("admin_reply")
    createdAt  DateTime? @map("created_at")
    updatedAt  DateTime? @map("updated_at")

    user       User?     @relation(fields: [userId], references: [id], onDelete: Cascade)

    @@map("contacts")
}

model PasswordResetToken {
    email     String   @id
    token     String
    createdAt DateTime? @map("created_at")

    @@map("password_reset_tokens")
}

model Session {
    id           String   @id
    userId       BigInt?  @map("user_id")
    ipAddress    String?  @map("ip_address") @db.VarChar(45)
    userAgent    String?  @map("user_agent")
    payload      String
    lastActivity Int      @map("last_activity")

    @@map("sessions")
}

model Cache {
    key        String   @id
    value      String
    expiration Int

    @@map("cache")
}

model CacheLock {
    key        String   @id
    owner      String
    expiration Int

    @@map("cache_locks")
}
```

Catatan:
- `products.category` masih disimpan sebagai string, belum relasi ke `categories`.
- `conversations.last_message_id` masih belum dibuat sebagai foreign key fisik.
- Beberapa status di migration disimpan sebagai string, jadi di schema Prisma ini tetap dipertahankan sebagai string agar sesuai kondisi project saat ini.
