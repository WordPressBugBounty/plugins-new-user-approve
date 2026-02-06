import { __ } from "@wordpress/i18n";
import IconButton from '@mui/material/IconButton';
import { QRCodeSVG } from 'qrcode.react';

const images = require.context(
  "../../assets/images",
  false,
  /\.(png|jpe?g|svg)$/
);

const MobileApp = () => {

  const appBannerImg = images(`./app-banner-img.png`);
  const androidBtn = images(`./android-btn.png`);
  const iosBtn = images(`./ios-btn.png`);

  return (
    <div className="nua-mobile-app-wrapper">
      <div className="nua-app-banner-wrapper">
        <div className="nua-app-banner-ls">
          <h1>{__( "Manage User Requests With Your Finger Tips.", "new-user-approve")}</h1>
          <p>{__( "Download the New User Approve Mobile App today!", "new-user-approve" )}</p>
          <div className="nua-app-banner-btns">
            <IconButton onClick={() => window.open( "https://play.google.com/store/apps/details?id=com.newuserapproveapp", "_blank") }>
              <img src={androidBtn} alt="Android Button" />
            </IconButton>
            <IconButton onClick={() => window.open( "https://apps.apple.com/app/new-user-approve/id6752776437", "_blank") }>
              <img src={iosBtn} alt="iOS Button" />
            </IconButton>
          </div>
        </div>
        <div className="nua-app-banner-rs">
          <img src={appBannerImg} alt="banner" />
        </div>
      </div>
      <div className="nua-app-primary-wrapper">
        <div className="nua-app-content">
          <ol type="1" className="nua-app-instructions">
            <li>{__( "Open 📱 ", "new-user-approve" )}<strong>{__( "NUA Mobile App", "new-user-approve" )}</strong>{__( " on your Android or iOS device.", "new-user-approve" )}</li>
            <li>{__( "Tap on ", "new-user-approve" )}<strong>{__( "scan QR code", "new-user-approve" )}</strong>{__( " button to integrate your mobile device with NUA Plugin.", "new-user-approve" )}</li>
            <li>{__( "Place your mobile device before this screen to ", "new-user-approve" )}<strong>{__( "capture the QR Code.", "new-user-approve" )}</strong></li>
          </ol>

          <p className="nua-app-p1">{__( "And you are done 👍", "new-user-approve" )}</p>

          <p className="nua-app-p2">
            {__( "Want more details? Check out our complete guide 📖 ", "new-user-approve" )}
            <strong>
              <a href="https://newuserapprove.com/documentation/app/" target="_blank" rel="noopener noreferrer">
                {__( "NUA plugin with mobile app", "new-user-approve" )}
              </a>
            </strong>
          </p>
        </div>
        <div className="nua-app-qr-code">
          <QRCodeSVG 
              value={siteDetail.siteUrl + "/?token=" + siteDetail.app_auth_token}
              size={200} 
              bgColor="#FFFFFF" 
              fgColor="#000000" 
              level="H"
          />
        </div>
      </div>
    </div>
  );
};

export default MobileApp;